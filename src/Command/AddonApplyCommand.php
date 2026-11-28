<?php

declare(strict_types=1);

namespace ESoul\AppTemplates\Command;

use ESoul\AppTemplates\AddonApplier;
use ESoul\AppTemplates\AddonInstaller;
use ESoul\AppTemplates\AddonLoader;
use ESoul\AppTemplates\AddonReport;
use ESoul\AppTemplates\AddonWorkspace;
use ESoul\AppTemplates\Catalog;
use ESoul\AppTemplates\ChangeKind;
use ESoul\AppTemplates\CommandRunner;
use ESoul\AppTemplates\ProcessCommandRunner;
use ESoul\AppTemplates\ProjectRecord;
use ESoul\AppTemplates\SourceRepository;
use ESoul\AppTemplates\TemplateException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'addon:apply', description: 'Apply an addon (name, directory or git URL) to an existing project')]
final class AddonApplyCommand extends Command
{
    private readonly CommandRunner $runner;

    public function __construct(private readonly string $root, ?CommandRunner $runner = null) {
        parent::__construct();
        $this->runner = $runner ?? new ProcessCommandRunner();
    }

    protected function configure(): void {
        $this
            ->addArgument('addon', InputArgument::REQUIRED, 'Name of an addon of this repository (addons/<name>.json), a directory or a git URL of an external addon')
            ->addArgument('project', InputArgument::REQUIRED, 'The project directory (it has .esoul/template.json)')
            ->addOption('no-install', null, InputOption::VALUE_NONE, 'Do not run composer update, pnpm install and esoul-guidelines sync afterwards')
            ->addOption(
                'guidelines-bin',
                null,
                InputOption::VALUE_REQUIRED,
                'esoul-guidelines installer used when the project has no vendor/bin/esoul-guidelines (relative paths resolve against the repository root)',
                'vendor/bin/esoul-guidelines',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);
        $catalog = new Catalog($this->root);
        $loader = new AddonLoader($catalog);

        try {
            $reference = $input->getArgument('addon');
            $project = $input->getArgument('project');
            if ( ! is_string($reference) || ! is_string($project)) {
                throw new TemplateException('Give the addon and the project directory.');
            }
            if ( ! is_dir($project)) {
                throw new TemplateException(sprintf('%s is not a directory.', $project));
            }

            $addon = $loader->load($reference);
            $record = ProjectRecord::read($project);
            $variant = $record->variant();
            $addon->layersFor($variant);

            $warning = $this->freshnessWarning($record->commit());
            if ($warning !== null) {
                $io->warning($warning);
            }

            // Everything is planned (and so validated) before the first write; the record goes in with the files.
            $workspace = new AddonWorkspace($project);
            $report = new AddonApplier($catalog)->apply($addon, $variant, $workspace, false);
            $workspace->write(ProjectRecord::PATH, $record->withAddon($addon)->encode());
            $workspace->flush();

            $io->writeln(sprintf('Applied addon <info>%s</info> to %s (variant %s).', $addon->name, $project, $variant));
            $this->printReport($io, $report);

            if ($input->getOption('no-install') !== true) {
                $guidelines = StringOption::get($input, 'guidelines-bin');
                if ( ! str_starts_with($guidelines, '/')) {
                    $guidelines = $this->root . '/' . $guidelines;
                }
                try {
                    foreach (new AddonInstaller($this->runner, $guidelines)->install($project, $report) as $command) {
                        $io->writeln(sprintf('Ran <comment>%s</comment>', $command));
                    }
                } catch (TemplateException $e) {
                    throw new TemplateException('The addon was applied and recorded, but an install step failed: ' . $e->getMessage(), 0, $e);
                }
            }

            if ($addon->steps !== []) {
                $io->section('Next steps');
                $io->listing($addon->steps);
            }
        } catch (TemplateException $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        } finally {
            $loader->release();
        }

        return self::SUCCESS;
    }

    /** Warns (never refuses) when the template changed after the commit the project is on; what that means is for the update skill to judge. */
    private function freshnessWarning(?string $commit): ?string {
        $source = new SourceRepository($this->root);
        $head = $source->headCommit();
        if ($head === null) {
            return null;
        }
        if ($commit === null) {
            return 'The project records no template commit, so it cannot be compared with this checkout. Update or adopt it first if the addon refuses a slot.';
        }
        if (str_starts_with($head, $commit)) {
            return null;
        }
        if ( ! $source->hasCommit($commit)) {
            return sprintf('The project\'s template commit %s is unknown in this checkout. The template update may be needed first.', $commit);
        }
        if ($source->changelogChangedSince($commit)) {
            return sprintf('CHANGELOG.md changed since the project\'s template commit %s. The template update may be needed first.', $commit);
        }

        return null;
    }

    private function printReport(SymfonyStyle $io, AddonReport $report): void {
        $any = false;
        foreach (ChangeKind::cases() as $kind) {
            $entries = $report->entries($kind);
            if ($entries === []) {
                continue;
            }

            $any = true;
            $io->writeln('<info>' . $kind->heading() . '</info>');
            foreach ($entries as $entry) {
                $io->writeln('  ' . $entry['path'] . ($entry['detail'] === '' ? '' : ' (' . $entry['detail'] . ')'));
            }
        }
        if ( ! $any) {
            $io->writeln('Nothing to change: the project already has everything the addon adds.');
        }
    }
}
