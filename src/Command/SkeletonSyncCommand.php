<?php

declare(strict_types=1);

namespace ESoul\AppTemplates\Command;

use ESoul\AppTemplates\SkeletonSync;
use ESoul\AppTemplates\TemplateException;
use LogicException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'skeleton:sync', description: 'Refresh layers/laravel/skeleton from upstream laravel/laravel')]
final class SkeletonSyncCommand extends Command
{
    public function __construct(private readonly string $root) {
        parent::__construct();
    }

    protected function configure(): void {
        $this->addOption('constraint', null, InputOption::VALUE_REQUIRED, 'Composer version constraint of laravel/laravel', '^13');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);

        $constraint = $input->getOption('constraint');
        if ( ! is_string($constraint)) {
            throw new LogicException('Option --constraint must have a string value.');
        }

        try {
            $result = new SkeletonSync($this->root)->sync($constraint, $io->writeln(...));
        } catch (TemplateException $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }

        $io->success(sprintf(
            'laravel/laravel %s synced: %d files kept, %d stripped (provided by common / laravel/base).',
            $result['version'],
            $result['files'],
            $result['stripped'],
        ));

        return self::SUCCESS;
    }
}
