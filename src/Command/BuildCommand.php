<?php

declare(strict_types=1);

namespace ESoul\AppTemplates\Command;

use ESoul\AppTemplates\Addon;
use ESoul\AppTemplates\AddonLoader;
use ESoul\AppTemplates\Builder;
use ESoul\AppTemplates\Catalog;
use ESoul\AppTemplates\CommandRunner;
use ESoul\AppTemplates\GuidelinesRenderer;
use ESoul\AppTemplates\PhpFormatter;
use ESoul\AppTemplates\ProcessCommandRunner;
use ESoul\AppTemplates\SourceRepository;
use ESoul\AppTemplates\TemplateException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'build', description: 'Build one variant (or all with --all) into <out>/<variant>')]
final class BuildCommand extends Command
{
    private readonly CommandRunner $runner;

    public function __construct(private readonly string $root, ?CommandRunner $runner = null) {
        $this->runner = $runner ?? new ProcessCommandRunner();
        parent::__construct();
    }

    protected function configure(): void {
        $this
            ->addArgument('variant', InputArgument::OPTIONAL, 'Variant name, see `templates list`')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Build every variant')
            ->addOption('out', null, InputOption::VALUE_REQUIRED, 'Output directory (relative paths resolve against the repository root)', 'build')
            ->addOption(
                'commit',
                null,
                InputOption::VALUE_REQUIRED,
                'app-templates source commit recorded in .esoul/template.json of every built variant (default: HEAD of this repository; no record when it is not a git checkout)',
            )
            ->addOption(
                'addon',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Addon applied on top of the variant: a name from addons/, a directory or a git URL (repeatable)',
            )
            ->addOption(
                'guidelines-bin',
                null,
                InputOption::VALUE_REQUIRED,
                'esoul-guidelines installer used to render the guidelines into each variant (relative paths resolve against the repository root)',
                'vendor/bin/esoul-guidelines',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);
        $catalog = new Catalog($this->root);

        $variant = $input->getArgument('variant');
        $variant = is_string($variant) ? $variant : null;
        $all = $input->getOption('all') === true;
        if ($all === ($variant !== null)) {
            $io->error('Give either a variant name or --all.');

            return self::INVALID;
        }

        $out = StringOption::get($input, 'out');
        if ( ! str_starts_with($out, '/')) {
            $out = $this->root . '/' . $out;
        }
        $commit = $input->getOption('commit');
        if ($commit !== null && ( ! is_string($commit) || ! SourceRepository::isCommitHash($commit))) {
            $io->error('--commit must be a git commit hash (7 to 40 lowercase hex characters).');

            return self::INVALID;
        }
        $commit ??= new SourceRepository($this->root)->headCommit();

        $names = $variant === null ? $catalog->variantNames() : [$variant];
        $guidelinesBin = StringOption::get($input, 'guidelines-bin');
        if ( ! str_starts_with($guidelinesBin, '/')) {
            $guidelinesBin = $this->root . '/' . $guidelinesBin;
        }
        $formatter = new PhpFormatter($this->runner);
        $builder = new Builder($catalog, new GuidelinesRenderer($guidelinesBin), $formatter);

        $loader = new AddonLoader($catalog);

        try {
            // Load every addon, and check it supports every variant, before anything is built.
            $addons = [];
            foreach ((array) $input->getOption('addon') as $reference) {
                if (is_string($reference)) {
                    $addons[] = $loader->load($reference);
                }
            }
            foreach ($names as $name) {
                foreach ($addons as $addon) {
                    $addon->layersFor($name);
                }
            }

            foreach ($names as $name) {
                $path = $builder->build($name, $out, $commit, $addons);
                $io->writeln(sprintf('Built <info>%s</info> → %s%s', $name, $path, $addons === [] ? '' : ' with ' . implode(', ', array_map(static fn (Addon $addon): string => $addon->name, $addons))));
            }
        } catch (TemplateException $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        } finally {
            $loader->release();
            $formatter->release();
        }

        return self::SUCCESS;
    }
}
