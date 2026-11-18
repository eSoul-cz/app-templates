<?php

declare(strict_types=1);

namespace ESoul\AppTemplates\Command;

use ESoul\AppTemplates\Builder;
use ESoul\AppTemplates\Catalog;
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
            ->addOption('release', null, InputOption::VALUE_REQUIRED, 'Write .esoul/template.json with this version into every built variant');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $catalog = new Catalog($this->root);

        $variant = $input->getArgument('variant');
        if ($input->getOption('all') === ($variant !== null)) {
            $io->error('Give either a variant name or --all.');

            return self::INVALID;
        }

        $out = (string) $input->getOption('out');
        if (!str_starts_with($out, '/')) {
            $out = $this->root . '/' . $out;
        }
        $version = $input->getOption('release');

        $names = $input->getOption('all') ? $catalog->variantNames() : [(string) $variant];
        $builder = new Builder($catalog);

        try {
            foreach ($names as $name) {
                $path = $builder->build($name, $out, is_string($version) ? $version : null);
                $io->writeln(sprintf('Built <info>%s</info> → %s', $name, $path));
            }
        } catch (TemplateException $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
