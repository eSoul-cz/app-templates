<?php

declare(strict_types=1);

namespace ESoul\AppTemplates\Tests;

use ESoul\AppTemplates\CommandRunner;
use ESoul\AppTemplates\TemplateException;

/** Records the commands `addon:apply` asks for instead of running Composer, pnpm or the guidelines installer. */
final class RecordingRunner implements CommandRunner
{
    /** @var list<list<string>> */
    public array $commands = [];

    /** When set, every command is recorded and then fails with this message. */
    public ?string $failure = null;

    public function run(array $command, string $directory): void {
        $this->commands[] = $command;

        if ($this->failure !== null) {
            throw new TemplateException($this->failure);
        }
    }
}
