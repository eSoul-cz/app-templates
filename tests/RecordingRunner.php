<?php

declare(strict_types=1);

namespace ESoul\AppTemplates\Tests;

use ESoul\AppTemplates\CommandRunner;

/** Records the commands `addon:apply` asks for instead of running Composer, pnpm or the guidelines installer. */
final class RecordingRunner implements CommandRunner
{
    /** @var list<list<string>> */
    public array $commands = [];

    public function run(array $command, string $directory): void {
        $this->commands[] = $command;
    }
}
