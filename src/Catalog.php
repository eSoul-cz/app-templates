<?php

declare(strict_types=1);

namespace ESoul\AppTemplates;

use stdClass;
use Symfony\Component\Finder\Finder;

/** Reads `variants/*.json` and locates `layers/<name>` of the source repository. */
final readonly class Catalog
{
    public function __construct(public string $root) {
    }

    /** @return list<string> sorted variant names */
    public function variantNames(): array {
        $dir = $this->root . '/variants';
        if ( ! is_dir($dir)) {
            return [];
        }

        $names = [];
        foreach (new Finder()->files()->in($dir)->depth(0)->name('*.json') as $file) {
            $names[] = $file->getBasename('.json');
        }
        sort($names);

        return $names;
    }

    public function variant(string $name): Variant {
        $file = $this->root . '/variants/' . $name . '.json';
        if (preg_match('/^[a-z0-9][a-z0-9-]*$/', $name) !== 1 || ! is_file($file)) {
            $available = $this->variantNames();
            throw new TemplateException(sprintf('Unknown variant "%s". Available: %s.', $name, $available === [] ? 'none' : implode(', ', $available)));
        }

        $data = JsonMerger::decode((string) file_get_contents($file), $file);
        if ( ! $data instanceof stdClass) {
            throw new TemplateException(sprintf('%s must contain a JSON object.', $file));
        }

        $variantName = self::requiredString($data, 'name', $file);
        $package = self::requiredString($data, 'package', $file);
        $description = self::requiredString($data, 'description', $file);
        if ($variantName !== $name) {
            throw new TemplateException(sprintf('%s: "name" is "%s" but the file is called "%s.json".', $file, $variantName, $name));
        }
        if ( ! isset($data->layers) || ! is_array($data->layers) || $data->layers === []) {
            throw new TemplateException(sprintf('%s: "layers" must be a non-empty list.', $file));
        }

        $entries = [];
        foreach ($data->layers as $index => $raw) {
            $entries[] = $this->entry($raw, sprintf('%s: layers[%d]', $file, $index));
        }

        return new Variant($variantName, $package, $description, $entries);
    }

    /** Absolute path of a layer directory; throws when the layer does not exist. */
    public function layerPath(string $layer): string {
        if ( ! self::isSafeRelativePath($layer)) {
            throw new TemplateException(sprintf('Invalid layer name "%s".', $layer));
        }

        $path = $this->root . '/layers/' . $layer;
        if ( ! is_dir($path)) {
            throw new TemplateException(sprintf('Unknown layer "%s" (expected directory layers/%s).', $layer, $layer));
        }

        return $path;
    }

    public static function isSafeRelativePath(string $path): bool {
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, '\\')) {
            return false;
        }

        return ! in_array('..', explode('/', $path), true) && ! in_array('.', explode('/', $path), true);
    }

    private function entry(mixed $raw, string $where): LayerEntry {
        if ( ! $raw instanceof stdClass || ! isset($raw->layer) || ! is_string($raw->layer)) {
            throw new TemplateException(sprintf('%s must be an object with a "layer" string.', $where));
        }

        $into = $raw->into ?? '';
        // `into` ends up unquoted in a composer script (`--working-dir=<into>`), so only shell-safe characters are allowed.
        if ( ! is_string($into) || ($into !== '' && ( ! self::isSafeRelativePath($into) || preg_match('#^[A-Za-z0-9._-]+(/[A-Za-z0-9._-]+)*/?$#D', $into) !== 1))) {
            throw new TemplateException(sprintf('%s: "into" must be a relative path without "..", made of letters, digits, ".", "_" and "-".', $where));
        }

        $globs = self::globList($raw->exclude ?? []);
        if ($globs === null) {
            throw new TemplateException(sprintf('%s: "exclude" must be a list of glob strings.', $where));
        }

        return new LayerEntry($raw->layer, trim($into, '/'), $globs);
    }

    /** @return list<string>|null the globs, or null when the value is not an array of strings */
    private static function globList(mixed $value): ?array {
        if ( ! is_array($value)) {
            return null;
        }

        $globs = [];
        foreach ($value as $glob) {
            if ( ! is_string($glob)) {
                return null;
            }
            $globs[] = $glob;
        }

        return $globs;
    }

    private static function requiredString(stdClass $data, string $key, string $file): string {
        $value = $data->{$key} ?? null;
        if ( ! is_string($value) || $value === '') {
            throw new TemplateException(sprintf('%s: "%s" must be a non-empty string.', $file, $key));
        }

        return $value;
    }
}
