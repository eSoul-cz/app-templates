<?php

declare(strict_types=1);

namespace ESoul\AppTemplates;

use Symfony\Component\Process\Exception\ProcessStartFailedException;
use Symfony\Component\Process\Process;

final readonly class ProcessCommandRunner implements CommandRunner
{
    public function run(array $command, string $directory): void {
        $process = new Process($command, $directory);
        $process->setTimeout(1800);

        try {
            $process->run();
        } catch (ProcessStartFailedException $e) {
            throw new TemplateException(sprintf('Could not run `%s`: %s', implode(' ', $command), $e->getMessage()), 0, $e);
        }

        if ( ! $process->isSuccessful()) {
            throw new TemplateException(sprintf(
                '`%s` failed in %s (exit %d):%s%s',
                implode(' ', $command),
                $directory,
                (int) $process->getExitCode(),
                PHP_EOL,
                trim($process->getErrorOutput() . PHP_EOL . $process->getOutput()),
            ));
        }
    }
}
