<?php

declare(strict_types=1);

namespace ESoul\AppTemplates;

use stdClass;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * Assembles a variant by overlaying its layers, in order, onto an empty directory.
 *
 * Per layer (inside the entry's `into` directory): `delete.txt`, then `files/`, then `merge/`, then `append/`.
 * `merge/esoul-guidelines.json` always merges into the variant root, so a monorepo has one guideline config.
 * Afterwards the root `composer.json` is stamped with the variant's package name.
 */
final readonly class Builder
{
    /** Project config of the esoul/guidelines installer; one per variant, at its root. */
    public const string GUIDELINES_CONFIG = 'esoul-guidelines.json';

    private Filesystem $fs;

    /** @param GuidelinesRenderer|null $guidelines renders the project guidelines after layering; null skips it (builder tests only) */
    public function __construct(private Catalog $catalog, private ?GuidelinesRenderer $guidelines = null)
    {
        $this->fs = new Filesystem();
    }

    /**
     * Build one variant into `<outDir>/<variant name>` (cleaned first) and return that path.
     *
     * @param string|null $version when set, `.esoul/template.json` {variant, version} is written into the result
     */
    public function build(string $variantName, string $outDir, ?string $version = null): string
    {
        $variant = $this->catalog->variant($variantName);

        // Resolve every layer first so an unknown layer fails before anything is deleted.
        $paths = [];
        foreach ($variant->layers as $entry) {
            $paths[] = $this->catalog->layerPath($entry->layer);
        }

        $target = rtrim($outDir, '/') . '/' . $variant->name;
        $this->fs->remove($target);
        $this->fs->mkdir($target);

        foreach ($variant->layers as $index => $entry) {
            $entryTarget = $entry->into === '' ? $target : $target . '/' . $entry->into;
            $this->fs->mkdir($entryTarget);
            $this->applyLayer($paths[$index], $entryTarget, $entry->exclude, $target);
        }

        $this->stampComposer($variant, $target);
        $this->guidelines?->render($target);

        if ($version !== null) {
            $this->fs->dumpFile(
                $target . '/.esoul/template.json',
                JsonMerger::encode(['variant' => $variant->name, 'version' => $version]),
            );
        }

        return $target;
    }

    /** @param list<string> $exclude */
    private function applyLayer(string $layerPath, string $target, array $exclude, string $root): void
    {
        $this->applyDeletes($layerPath, $target);

        foreach ($this->relativeFiles($layerPath . '/files', $exclude) as $relative => $source) {
            $this->fs->copy($source, $target . '/' . $relative, true);
        }

        foreach ($this->relativeFiles($layerPath . '/merge', $exclude) as $relative => $source) {
            if (!str_ends_with($relative, '.json')) {
                throw new TemplateException(sprintf('Only .json files can be merged, found %s.', $source));
            }
            $destination = $relative === self::GUIDELINES_CONFIG ? $root : $target;
            $this->mergeJson($source, $destination . '/' . $relative);
        }

        foreach ($this->relativeFiles($layerPath . '/append', $exclude) as $relative => $source) {
            $this->appendLines($source, $target . '/' . $relative);
        }
    }

    private function applyDeletes(string $layerPath, string $target): void
    {
        $file = $layerPath . '/delete.txt';
        if (!is_file($file)) {
            return;
        }

        $patterns = [];
        foreach (preg_split('/\R/', (string) file_get_contents($file)) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '' && !str_starts_with($line, '#')) {
                $patterns[] = $line;
            }
        }
        if ($patterns === []) {
            return;
        }

        $doomed = [];
        $finder = (new Finder())->in($target)->ignoreDotFiles(false)->ignoreVCS(false)->ignoreUnreadableDirs();
        foreach ($finder as $item) {
            $relative = str_replace('\\', '/', $item->getRelativePathname());
            if (Glob::matchesAny($patterns, $relative)) {
                $doomed[] = $item->getPathname();
            }
        }

        // Directories are removed recursively; entries already gone with their parent are ignored by remove().
        $this->fs->remove($doomed);
    }

    /**
     * @param list<string> $exclude
     *
     * @return array<string, string> relative path => absolute source path, sorted by relative path
     */
    private function relativeFiles(string $dir, array $exclude): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $files = [];
        $finder = (new Finder())->files()->in($dir)->ignoreDotFiles(false)->ignoreVCS(false);
        foreach ($finder as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            if (!Glob::matchesAny($exclude, $relative)) {
                $files[$relative] = $file->getPathname();
            }
        }
        ksort($files, SORT_STRING);

        return $files;
    }

    private function mergeJson(string $source, string $destination): void
    {
        $overlay = JsonMerger::decode((string) file_get_contents($source), $source);

        if (is_file($destination)) {
            $base = JsonMerger::decode((string) file_get_contents($destination), $destination);
            $overlay = JsonMerger::merge($base, $overlay);
        }

        $this->fs->dumpFile($destination, JsonMerger::encode($overlay));
    }

    private function appendLines(string $source, string $destination): void
    {
        $existing = is_file($destination) ? (string) file_get_contents($destination) : '';
        $lines = $existing === '' ? [] : (preg_split('/\R/', rtrim($existing, "\r\n")) ?: []);
        $present = array_fill_keys(array_map(rtrim(...), $lines), true);

        foreach (preg_split('/\R/', rtrim((string) file_get_contents($source), "\r\n")) ?: [] as $line) {
            $line = rtrim($line);

            if ($line === '') {
                // Keep section spacing but never stack blank lines or start a file with one.
                if ($lines !== [] && end($lines) !== '') {
                    $lines[] = '';
                }
                continue;
            }
            if (isset($present[$line])) {
                continue;
            }

            $lines[] = $line;
            $present[$line] = true;
        }

        while ($lines !== [] && end($lines) === '') {
            array_pop($lines);
        }

        $this->fs->dumpFile($destination, $lines === [] ? '' : implode("\n", $lines) . "\n");
    }

    /**
     * Make the root composer.json carry the variant's package name and the MIT licence the variant
     * repositories are published under (created when no layer ships one; `common` ships LICENSE).
     */
    private function stampComposer(Variant $variant, string $target): void
    {
        $file = $target . '/composer.json';
        $composer = is_file($file) ? JsonMerger::decode((string) file_get_contents($file), $file) : new stdClass();
        if (!$composer instanceof stdClass) {
            throw new TemplateException(sprintf('%s must contain a JSON object.', $file));
        }

        $stamped = new stdClass();
        $stamped->name = $variant->package;
        $stamped->description = $variant->description;
        foreach (get_object_vars($composer) as $key => $value) {
            if ($key !== 'name' && $key !== 'description') {
                $stamped->{$key} = $value;
            }
        }
        $stamped->type ??= 'project';
        $stamped->license = 'MIT';

        $this->fs->dumpFile($file, JsonMerger::encode($stamped));
    }
}
