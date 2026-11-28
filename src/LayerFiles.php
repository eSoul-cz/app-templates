<?php

declare(strict_types=1);

namespace ESoul\AppTemplates;

use Symfony\Component\Finder\Finder;

/** Lists the files of one operation directory of a layer (`files/`, `merge/`, `append/`, `insert/`). */
final class LayerFiles
{
    /**
     * @param list<string> $exclude globs, relative to `$dir`, that are skipped
     *
     * @return array<string, string> relative path => absolute source path, sorted by relative path
     */
    public static function list(string $dir, array $exclude = []): array {
        if ( ! is_dir($dir)) {
            return [];
        }

        $files = [];
        $finder = new Finder()->files()->in($dir)->ignoreDotFiles(false)->ignoreVCS(false);
        foreach ($finder as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            if ( ! Glob::matchesAny($exclude, $relative)) {
                $files[$relative] = $file->getPathname();
            }
        }
        ksort($files, SORT_STRING);

        return $files;
    }
}
