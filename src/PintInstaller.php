<?php

declare(strict_types=1);

namespace ESoul\AppTemplates;

use Symfony\Component\Filesystem\Filesystem;

/** Installs Laravel Pint into a directory of its own, for the builder to format with when the target has no `vendor/`. */
final readonly class PintInstaller
{
    public function __construct(private CommandRunner $runner) {
    }

    /** @return string the Pint script, to run with PHP */
    public function install(string $directory): string {
        new Filesystem()->dumpFile($directory . '/composer.json', "{}\n");
        $this->runner->run(['composer', 'require', 'laravel/pint', '--working-dir=' . $directory, '--no-interaction', '--no-progress'], $directory);

        return $directory . '/vendor/bin/pint';
    }
}
