<?php

declare(strict_types=1);

namespace ESoul\AppTemplates;

/** Runs the commands `addon:apply` needs after changing a project (Composer, pnpm, the guidelines installer). */
interface CommandRunner
{
    /**
     * @param list<string> $command
     *
     * @throws TemplateException when the command cannot be started or exits with an error
     */
    public function run(array $command, string $directory): void;
}
