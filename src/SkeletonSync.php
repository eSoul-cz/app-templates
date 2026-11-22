<?php

declare(strict_types=1);

namespace ESoul\AppTemplates;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

/**
 * Refreshes `layers/laravel/skeleton/files` from upstream `laravel/laravel`.
 *
 * Files that a later layer provides itself (`common` and `laravel/base` `files/`) are stripped so the skeleton
 * never carries content that is overwritten anyway. Re-run the sync after those layers gain or lose files.
 */
final readonly class SkeletonSync
{
    /** Layers whose `files/` shadow the skeleton, see the variant layer order. */
    private const array SHADOWING_LAYERS = ['common', 'laravel/base'];

    private Filesystem $fs;

    public function __construct(private string $root) {
        $this->fs = new Filesystem();
    }

    /**
     * @param callable(string): void|null $log
     *
     * @return array{version: string, files: int, stripped: int}
     */
    public function sync(string $constraint = '^13', ?callable $log = null): array {
        $log ??= static function (string $message): void {
        };

        $tmp = sys_get_temp_dir() . '/esoul-skeleton-' . bin2hex(random_bytes(4));
        $project = $tmp . '/app';

        try {
            $log(sprintf('Downloading laravel/laravel %s ...', $constraint));
            $process = new Process([
                'composer', 'create-project', 'laravel/laravel', $project, $constraint,
                '--no-install', '--no-scripts', '--prefer-dist', '--no-interaction', '--no-progress',
            ], timeout: 300);
            $process->run();
            $output = $process->getOutput() . $process->getErrorOutput();

            if ( ! $process->isSuccessful()) {
                throw new TemplateException('composer create-project failed:' . "\n" . $output);
            }
            if (preg_match('/Installing laravel\/laravel \(v?([0-9][^)\s]*)\)/', $output, $matches) !== 1) {
                throw new TemplateException("Could not detect the resolved laravel/laravel version from composer output:\n" . $output);
            }
            $version = $matches[1];

            // Composer leaves the `.git` of a source install or nothing for dist; make sure no VCS data leaks in.
            $this->fs->remove($project . '/.git');

            $layerDir = $this->root . '/layers/laravel/skeleton';
            $files = $layerDir . '/files';
            $this->fs->remove($files);
            $this->fs->mirror($project, $files);

            $stripped = $this->strip($files);
            $this->format($files, $tmp . '/pint', $log);
            $count = iterator_count(new Finder()->files()->in($files)->ignoreDotFiles(false)->ignoreVCS(false));

            $this->fs->dumpFile($layerDir . '/layer.json', JsonMerger::encode([
                'description' => 'Upstream laravel/laravel skeleton, refreshed by `bin/templates skeleton:sync`. Do not edit by hand.',
                'upstream' => $version,
            ]));

            return ['version' => $version, 'files' => $count, 'stripped' => $stripped];
        } finally {
            $this->fs->remove($tmp);
        }
    }

    /**
     * Make the skeleton pass `composer cs` of the Laravel base layer by running Pint with that layer's pint.json.
     *
     * @param callable(string): void $log
     */
    private function format(string $skeletonFiles, string $pintDir, callable $log): void {
        $config = $this->root . '/layers/laravel/base/files/pint.json';
        if ( ! is_file($config)) {
            $log('layers/laravel/base/files/pint.json not found, skipping Pint.');

            return;
        }

        $log('Formatting skeleton with Pint ...');
        $this->fs->dumpFile($pintDir . '/composer.json', "{}\n");
        $this->run(['composer', 'require', 'laravel/pint', '--working-dir=' . $pintDir, '--no-interaction', '--no-progress'], null, 'composer require laravel/pint');
        $this->run([PHP_BINARY, $pintDir . '/vendor/bin/pint', '--config', $config], $skeletonFiles, 'pint');
    }

    /** @param list<string> $command */
    private function run(array $command, ?string $cwd, string $what): void {
        $process = new Process($command, $cwd, timeout: 300);
        $process->run();
        if ( ! $process->isSuccessful()) {
            throw new TemplateException($what . ' failed:' . "\n" . $process->getOutput() . $process->getErrorOutput());
        }
    }

    private function strip(string $skeletonFiles): int {
        $stripped = 0;
        foreach (self::SHADOWING_LAYERS as $layer) {
            $dir = $this->root . '/layers/' . $layer . '/files';
            if ( ! is_dir($dir)) {
                continue;
            }
            foreach (new Finder()->files()->in($dir)->ignoreDotFiles(false)->ignoreVCS(false) as $file) {
                $path = $skeletonFiles . '/' . $file->getRelativePathname();
                if (is_file($path)) {
                    $this->fs->remove($path);
                    $stripped++;
                }
            }
        }

        // Drop directories emptied by stripping.
        $dirs = iterator_to_array(new Finder()->directories()->in($skeletonFiles)->ignoreDotFiles(false)->ignoreVCS(false)->sortByName(), false);
        foreach (array_reverse($dirs) as $dir) {
            if ( ! new Finder()->in($dir->getPathname())->ignoreDotFiles(false)->ignoreVCS(false)->hasResults()) {
                $this->fs->remove($dir->getPathname());
            }
        }

        return $stripped;
    }
}
