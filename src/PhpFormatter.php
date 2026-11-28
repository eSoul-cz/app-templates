<?php

declare(strict_types=1);

namespace ESoul\AppTemplates;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Runs the project's formatter (Laravel Pint) on the PHP files an addon inserted into, so that an addon may insert idiomatic PHP
 * (`use \X\Trait;`, `\X\Y::class,`) and Pint moves the names into imports outside the markers. The project's own `vendor/bin/pint` is used
 * when it exists; otherwise, when the project has a `pint.json`, a Pint installed into a temporary directory (once per instance)
 * formats with that configuration. Without either nothing runs.
 */
final class PhpFormatter
{
    /**
     * Pint needs two passes after an insert: its class-attribute separation runs before the trait statements are reordered, so one pass
     * can leave a file that `pint --test` still rejects. The second pass changes nothing more (a third was measured to be a no-op).
     * Every pass gets a cache file of its own and deleted afterwards: Pint stores the result of a pass as "clean", so the next pass
     * would skip the file.
     */
    public const int PASSES = 2;

    private ?string $directory = null;

    private ?string $pint = null;

    public function __construct(private readonly CommandRunner $runner) {
    }

    public function __destruct() {
        $this->release();
    }

    /**
     * @param list<string> $files paths relative to `$root`; only the `.php` ones are formatted
     *
     * @return list<string> the files that were formatted, empty when no formatter ran
     *
     * @throws TemplateException when the formatter cannot be installed or fails
     */
    public function format(string $root, array $files): array {
        $php = array_values(array_unique(array_filter($files, static fn (string $file): bool => str_ends_with($file, '.php'))));
        if ($php === []) {
            return [];
        }

        $own = $root . '/vendor/bin/pint';
        $config = $root . '/pint.json';
        if ( ! is_file($own) && ! is_file($config)) {
            return [];
        }

        try {
            $command = is_file($own) ? [PHP_BINARY, $own] : [PHP_BINARY, $this->temporaryPint(), '--config', $config];
            for ($pass = 0; $pass < self::PASSES; $pass++) {
                $cache = sys_get_temp_dir() . '/esoul-pint-cache-' . bin2hex(random_bytes(6));
                try {
                    $this->runner->run([...$command, '--cache-file=' . $cache, ...$php], $root);
                } finally {
                    new Filesystem()->remove($cache);
                }
            }
        } catch (TemplateException $e) {
            throw new TemplateException(sprintf('Pint failed on %s: %s', implode(', ', $php), $e->getMessage()), 0, $e);
        }

        return $php;
    }

    /** Remove the temporary Pint. */
    public function release(): void {
        if ($this->directory !== null) {
            new Filesystem()->remove($this->directory);
        }
        $this->directory = null;
        $this->pint = null;
    }

    private function temporaryPint(): string {
        if ($this->pint === null) {
            $this->directory = sys_get_temp_dir() . '/esoul-pint-' . bin2hex(random_bytes(6));
            $this->pint = new PintInstaller($this->runner)->install($this->directory);
        }

        return $this->pint;
    }
}
