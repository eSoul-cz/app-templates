<?php

declare(strict_types=1);

namespace ESoul\AppTemplates\Command;

use ESoul\AppTemplates\Builder;
use ESoul\AppTemplates\Catalog;
use ESoul\AppTemplates\GuidelinesRenderer;
use ESoul\AppTemplates\TemplateException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

#[AsCommand(name: 'build', description: 'Build one variant (or all with --all) into <out>/<variant>')]
final class BuildCommand extends Command
{
    public function __construct(private readonly string $root)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
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
                'guidelines-bin',
                null,
                InputOption::VALUE_REQUIRED,
                'esoul-guidelines installer used to render the guidelines into each variant (relative paths resolve against the repository root)',
                'vendor/bin/esoul-guidelines',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $catalog = new Catalog($this->root);

        $variant = $input->getArgument('variant');
        $variant = is_string($variant) ? $variant : null;
        $all = $input->getOption('all') === true;
        if ($all === ($variant !== null)) {
            $io->error('Give either a variant name or --all.');

            return self::INVALID;
        }

        $out = $this->stringOption($input, 'out');
        if (!str_starts_with($out, '/')) {
            $out = $this->root . '/' . $out;
        }
        $commit = $input->getOption('commit');
        if ($commit !== null && (!is_string($commit) || preg_match('/^[0-9a-f]{7,40}$/', $commit) !== 1)) {
            $io->error('--commit must be a git commit hash (7 to 40 lowercase hex characters).');

            return self::INVALID;
        }
        $commit ??= $this->headCommit();

        $names = $variant === null ? $catalog->variantNames() : [$variant];
        $guidelinesBin = $this->stringOption($input, 'guidelines-bin');
        if (!str_starts_with($guidelinesBin, '/')) {
            $guidelinesBin = $this->root . '/' . $guidelinesBin;
        }
        $builder = new Builder($catalog, new GuidelinesRenderer($guidelinesBin));

        try {
            foreach ($names as $name) {
                $path = $builder->build($name, $out, $commit);
                $io->writeln(sprintf('Built <info>%s</info> → %s', $name, $path));
            }
        } catch (TemplateException $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /** Value of an option that is always a string: it takes a value and has a default. */
    private function stringOption(InputInterface $input, string $name): string
    {
        $value = $input->getOption($name);
        if (!is_string($value)) {
            throw new \LogicException(sprintf('Option --%s must have a string value.', $name));
        }

        return $value;
    }

    /** HEAD of the source repository, or null when `root` is not the top level of a git checkout (a parent repository must not leak in). */
    private function headCommit(): ?string
    {
        $top = new Process(['git', 'rev-parse', '--show-toplevel'], $this->root);
        $top->run();
        if (!$top->isSuccessful() || realpath(trim($top->getOutput())) !== realpath($this->root)) {
            return null;
        }

        $head = new Process(['git', 'rev-parse', 'HEAD'], $this->root);
        $head->run();

        return $head->isSuccessful() ? trim($head->getOutput()) : null;
    }
}
