<?php

declare(strict_types=1);

namespace ESoul\AppTemplates\Command;

use ESoul\AppTemplates\Catalog;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'list', description: 'List the template variants and their layers')]
final class ListCommand extends Command
{
    public function __construct(private readonly string $root) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);
        $catalog = new Catalog($this->root);

        $rows = [];
        foreach ($catalog->variantNames() as $name) {
            $variant = $catalog->variant($name);
            $layers = array_map(
                static fn ($entry): string => $entry->layer . ($entry->into !== '' ? ' → ' . $entry->into : ''),
                $variant->layers,
            );
            $rows[] = [$variant->name, $variant->package, implode(', ', $layers)];
        }

        $io->table(['Variant', 'Package', 'Layers'], $rows);

        return self::SUCCESS;
    }
}
