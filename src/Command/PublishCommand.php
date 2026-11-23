<?php

declare(strict_types=1);

namespace ESoul\AppTemplates\Command;

use ESoul\AppTemplates\Builder;
use ESoul\AppTemplates\Catalog;
use ESoul\AppTemplates\GuidelinesRenderer;
use ESoul\AppTemplates\Publisher;
use ESoul\AppTemplates\PublishResult;
use ESoul\AppTemplates\SourceRepository;
use ESoul\AppTemplates\TemplateException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(name: 'publish', description: 'Build a variant and sync it as one commit into the main branch of its template repository')]
final class PublishCommand extends Command
{
    public function __construct(private readonly string $root) {
        parent::__construct();
    }

    protected function configure(): void {
        $this
            ->addArgument('variant', InputArgument::REQUIRED, 'Variant name, see `templates list`')
            ->addOption('repo', null, InputOption::VALUE_REQUIRED, 'Target repository URL or path (default: https://github.com/<package of the variant>.git)')
            ->addOption('branch', null, InputOption::VALUE_REQUIRED, 'Branch of the target repository', 'main')
            ->addOption('message', null, InputOption::VALUE_REQUIRED, 'Commit subject (default: the subject of the source commit)')
            ->addOption('commit', null, InputOption::VALUE_REQUIRED, 'App-templates source commit to record (default: HEAD of this repository)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Build and compare with the remote, but push nothing')
            ->addOption(
                'guidelines-bin',
                null,
                InputOption::VALUE_REQUIRED,
                'esoul-guidelines installer used to render the guidelines (relative paths resolve against the repository root)',
                'vendor/bin/esoul-guidelines',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);
        $catalog = new Catalog($this->root);
        $source = new SourceRepository($this->root);

        $name = $input->getArgument('variant');
        if ( ! is_string($name)) {
            $io->error('Give a variant name.');

            return self::INVALID;
        }

        $commit = $input->getOption('commit');
        if ($commit !== null && ( ! is_string($commit) || ! SourceRepository::isCommitHash($commit))) {
            $io->error('--commit must be a git commit hash (7 to 40 lowercase hex characters).');

            return self::INVALID;
        }
        $commit ??= $source->headCommit();
        if ($commit === null) {
            $io->error('The source commit is unknown: run from a git checkout of app-templates or pass --commit.');

            return self::INVALID;
        }

        $guidelinesBin = StringOption::get($input, 'guidelines-bin');
        if ( ! str_starts_with($guidelinesBin, '/')) {
            $guidelinesBin = $this->root . '/' . $guidelinesBin;
        }

        $repo = $input->getOption('repo');
        $message = $input->getOption('message');
        $dryRun = $input->getOption('dry-run') === true;
        $fs = new Filesystem();
        $out = sys_get_temp_dir() . '/esoul-templates-publish-' . bin2hex(random_bytes(6));

        try {
            $variant = $catalog->variant($name);
            // The Composer vendor is lowercase (`esoul-cz`); the GitHub organisation is `eSoul-cz`, which avoids GitHub's "repository moved" redirect.
            $repo = is_string($repo) ? $repo : sprintf('https://github.com/eSoul-cz/%s.git', basename($variant->package));
            $message = is_string($message) ? $message : ($source->subject($commit) ?? sprintf('Sync %s from app-templates %s', $name, substr($commit, 0, 7)));

            // Always a fresh build: what is published is never a directory the variant's own checks have installed into.
            $built = new Builder($catalog, new GuidelinesRenderer($guidelinesBin))->build($name, $out, $commit);
            $result = new Publisher()->publish($built, $repo, StringOption::get($input, 'branch'), $message, $dryRun);
        } catch (TemplateException $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        } finally {
            $fs->remove($out);
        }

        $io->writeln($this->describe($name, $repo, $result, $dryRun));

        return self::SUCCESS;
    }

    private function describe(string $variant, string $repo, PublishResult $result, bool $dryRun): string {
        if ($result->status === PublishResult::UNCHANGED) {
            return sprintf('<info>%s</info> is up to date in %s (%d files); nothing pushed.', $variant, $repo, $result->files);
        }

        return sprintf(
            '%s <info>%s</info> → %s as %s (%d files).',
            $dryRun ? 'Dry run, would push' : ($result->status === PublishResult::CREATED ? 'Created' : 'Published'),
            $variant,
            $repo,
            substr((string) $result->commit, 0, 12),
            $result->files,
        );
    }
}
