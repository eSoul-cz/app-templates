<?php

declare(strict_types=1);

namespace ESoul\AppTemplates;

use Symfony\Component\Process\Process;

/**
 * Renders the project guidelines into a built variant by running the esoul-cz/guidelines installer
 * (`esoul-guidelines sync`) in its root, so a variant repository ships `docs/project_guidelines/`,
 * the AGENTS.md block and `esoul-guidelines.lock` ready-made.
 */
final readonly class GuidelinesRenderer
{
    /** @param string $binary path to the `esoul-guidelines` PHP script (usually `vendor/bin/esoul-guidelines`) */
    public function __construct(private string $binary) {
    }

    public function render(string $target): void {
        if ( ! is_file($this->binary)) {
            throw new TemplateException(sprintf(
                'The guidelines installer was not found at %s. Run `composer install` (it requires esoul-cz/guidelines) or pass --guidelines-bin.',
                $this->binary,
            ));
        }
        if ( ! is_file($target . '/' . Builder::GUIDELINES_CONFIG)) {
            throw new TemplateException(sprintf('%s has no %s; add the guideline layers through a layer\'s merge/.', $target, Builder::GUIDELINES_CONFIG));
        }

        $process = new Process([PHP_BINARY, $this->binary, 'sync', '--no-interaction'], $target);
        $process->setTimeout(120);
        $process->run();

        if ( ! $process->isSuccessful()) {
            throw new TemplateException(sprintf(
                'esoul-guidelines sync failed in %s (exit %d):%s%s',
                $target,
                (int) $process->getExitCode(),
                PHP_EOL,
                trim($process->getErrorOutput() . PHP_EOL . $process->getOutput()),
            ));
        }
    }
}
