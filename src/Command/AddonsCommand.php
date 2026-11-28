<?php

declare(strict_types=1);

namespace ESoul\AppTemplates\Command;

use ESoul\AppTemplates\AddonLoader;
use ESoul\AppTemplates\Catalog;
use ESoul\AppTemplates\JsonMerger;
use ESoul\AppTemplates\TemplateException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'addons', description: 'List the addons of this repository and the variants each declares')]
final class AddonsCommand extends Command
{
    public function __construct(private readonly string $root) {
        parent::__construct();
    }

    protected function configure(): void {
        $this->addOption(
            'json',
            null,
            InputOption::VALUE_NONE,
            'Print a JSON list of {"name", "description", "variants": [variant, ...]} instead of a table (the CI reads it)',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);
        $loader = new AddonLoader(new Catalog($this->root));

        try {
            /** @var list<array{name: string, description: string, variants: list<string>}> $addons */
            $addons = [];
            foreach ($loader->names() as $name) {
                $addon = $loader->inRepository($name);
                $addons[] = ['name' => $addon->name, 'description' => $addon->description, 'variants' => array_keys($addon->variants)];
            }
        } catch (TemplateException $exception) {
            $io->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($input->getOption('json') === true) {
            $output->write(JsonMerger::encode($addons), false, OutputInterface::OUTPUT_RAW);

            return self::SUCCESS;
        }

        $io->table(
            ['Addon', 'Variants', 'Description'],
            array_map(static fn ($addon): array => [$addon['name'], implode(', ', $addon['variants']), $addon['description']], $addons),
        );

        return self::SUCCESS;
    }
}
