<?php

declare(strict_types=1);

namespace ESoul\AppTemplates;

use stdClass;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

/**
 * Loads and validates addons: `addons/<name>.json` of this repository, or an external addon, a directory or git repository whose root
 * has `addon.json` and `layers/`. Git repositories are cloned (shallow) into a temporary directory that {@see release()} removes.
 */
final class AddonLoader
{
    public const string EXTERNAL_MANIFEST = 'addon.json';

    private const array MANIFEST_KEYS = ['name', 'description', 'variants', 'steps'];

    /** @var list<string> */
    private array $temporary = [];

    public function __construct(private readonly Catalog $catalog) {
    }

    public function __destruct() {
        $this->release();
    }

    /** @return list<string> sorted names of the addons of this repository */
    public function names(): array {
        $dir = $this->catalog->root . '/addons';
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

    /** `<name>` of this repository if `addons/<name>.json` exists, else an existing directory, else a git URL. */
    public function load(string $reference): Addon {
        if ($reference === '') {
            throw new TemplateException('The addon must be a name, a directory or a git URL.');
        }
        if (self::isName($reference) && is_file($this->manifestOf($reference))) {
            return $this->inRepository($reference);
        }
        // `file:///path` is a directory to PHP's stream wrappers but a git URL here.
        if ( ! str_contains($reference, '://') && is_dir($reference)) {
            return $this->external($reference, $reference, new SourceRepository($reference)->headCommit());
        }

        $directory = $this->clone($reference);

        return $this->external($directory, $reference, new SourceRepository($directory)->headCommit());
    }

    public function inRepository(string $name): Addon {
        $file = $this->manifestOf($name);
        if ( ! self::isName($name) || ! is_file($file)) {
            $names = $this->names();
            throw new TemplateException(sprintf('Unknown addon "%s". Available: %s.', $name, $names === [] ? 'none' : implode(', ', $names)));
        }

        return $this->parse($file, $name, $this->catalog->root . '/layers', null, null);
    }

    /** Remove the clones of git addons. */
    public function release(): void {
        $fs = new Filesystem();
        foreach ($this->temporary as $directory) {
            $fs->remove($directory);
        }
        $this->temporary = [];
    }

    private static function isName(string $name): bool {
        return preg_match('/^[a-z0-9][a-z0-9-]*$/D', $name) === 1;
    }

    private function manifestOf(string $name): string {
        return $this->catalog->root . '/addons/' . $name . '.json';
    }

    private function external(string $directory, string $source, ?string $commit): Addon {
        $file = $directory . '/' . self::EXTERNAL_MANIFEST;
        if ( ! is_file($file)) {
            throw new TemplateException(sprintf('%s has no %s at its root, so it is not an addon.', $source, self::EXTERNAL_MANIFEST));
        }

        return $this->parse($file, null, $directory . '/layers', $source, $commit);
    }

    private function clone(string $url): string {
        if (str_starts_with($url, '-')) {
            throw new TemplateException(sprintf('"%s" is neither an addon name, a directory nor a git URL.', $url));
        }

        $directory = sys_get_temp_dir() . '/esoul-addon-' . bin2hex(random_bytes(6));
        $this->temporary[] = $directory;

        $git = new Process(['git', 'clone', '--depth', '1', '--quiet', '--', $url, $directory], null, ['GIT_TERMINAL_PROMPT' => '0']);
        $git->setTimeout(300);
        $git->run();
        if ( ! $git->isSuccessful()) {
            $names = $this->names();

            throw new TemplateException(sprintf(
                '"%s" is not an addon of this repository (%s), not a directory, and cloning it failed:%s%s',
                $url,
                $names === [] ? 'none exist' : 'available: ' . implode(', ', $names),
                PHP_EOL,
                trim($git->getErrorOutput()),
            ));
        }

        return $directory;
    }

    private function parse(string $file, ?string $expectedName, string $layerRoot, ?string $source, ?string $commit): Addon {
        $data = JsonMerger::decode((string) file_get_contents($file), $file);
        if ( ! $data instanceof stdClass) {
            throw new TemplateException(sprintf('%s must contain a JSON object.', $file));
        }

        $unknown = array_values(array_diff(array_map(strval(...), array_keys(get_object_vars($data))), self::MANIFEST_KEYS));
        if ($unknown !== []) {
            throw new TemplateException(sprintf('%s: unknown key "%s". Allowed: %s.', $file, $unknown[0], implode(', ', self::MANIFEST_KEYS)));
        }

        $name = self::string($data, 'name', $file);
        $description = self::string($data, 'description', $file);
        if ( ! self::isName($name)) {
            throw new TemplateException(sprintf('%s: "name" must be lowercase letters, digits and "-", got "%s".', $file, $name));
        }
        if ($expectedName !== null && $name !== $expectedName) {
            throw new TemplateException(sprintf('%s: "name" is "%s" but the file is called "%s.json".', $file, $name, $expectedName));
        }

        $steps = [];
        foreach (self::list($data->steps ?? [], $file, 'steps') as $step) {
            $steps[] = $step;
        }

        $addon = new Addon($name, $description, $this->variants($data, $file, $layerRoot), $steps, $layerRoot, $source, $commit);
        $this->validateLayers($addon);

        return $addon;
    }

    /** @return array<string, list<string>> */
    private function variants(stdClass $data, string $file, string $layerRoot): array {
        $declared = $data->variants ?? null;
        if ( ! $declared instanceof stdClass || get_object_vars($declared) === []) {
            throw new TemplateException(sprintf('%s: "variants" must be a non-empty object (variant => list of layers).', $file));
        }

        $existing = $this->catalog->variantNames();
        $variants = [];
        foreach (get_object_vars($declared) as $variant => $layers) {
            $variant = (string) $variant;
            if ( ! in_array($variant, $existing, true)) {
                throw new TemplateException(sprintf('%s: unknown variant "%s". Available: %s.', $file, $variant, implode(', ', $existing)));
            }

            $names = self::list($layers, $file, 'variants.' . $variant);
            if ($names === []) {
                throw new TemplateException(sprintf('%s: "variants.%s" must be a non-empty list of layers.', $file, $variant));
            }
            foreach ($names as $layer) {
                if ( ! Catalog::isSafeRelativePath($layer) || ! is_dir($layerRoot . '/' . $layer)) {
                    throw new TemplateException(sprintf('%s: unknown layer "%s" in "variants.%s" (expected directory %s/%s).', $file, $layer, $variant, $layerRoot, $layer));
                }
            }
            $variants[$variant] = $names;
        }

        return $variants;
    }

    /** Checks that need no project: the restrictions of an addon layer. */
    private function validateLayers(Addon $addon): void {
        $layers = array_unique(array_merge(...array_values($addon->variants)));

        foreach ($layers as $layer) {
            $path = $addon->layerPath($layer);
            $where = sprintf('Addon "%s", layer "%s"', $addon->name, $layer);

            if (is_file($path . '/delete.txt')) {
                throw new TemplateException(sprintf('%s: delete.txt is not allowed, an addon only adds.', $where));
            }

            foreach (LayerFiles::list($path . '/merge') as $relative => $source) {
                if ( ! str_ends_with($relative, '.json')) {
                    throw new TemplateException(sprintf('%s: only .json files can be merged, found %s.', $where, $source));
                }
                $overlay = JsonMerger::decode((string) file_get_contents($source), $source);
                FillMerger::assertNoNull($overlay, $source);
                if ( ! $addon->isExternal() && FillMerger::contains($overlay, Addon::ADDON_PLACEHOLDER)) {
                    throw new TemplateException(sprintf(
                        '%s: %s uses "%s", which only an external addon may use (it stands for the URL the addon was applied from).',
                        $where,
                        $source,
                        Addon::ADDON_PLACEHOLDER,
                    ));
                }
            }

            foreach (array_keys(LayerFiles::list($path . '/insert')) as $relative) {
                $parts = explode('/', $relative, 2);
                if (count($parts) !== 2 || ! SlotInserter::isValidSlotName($parts[0])) {
                    throw new TemplateException(sprintf('%s: insert/%s must be insert/<slot>/<path>, with a slot made of letters, digits, "_" and "-".', $where, $relative));
                }
            }
        }
    }

    private static function string(stdClass $data, string $key, string $file): string {
        $value = $data->{$key} ?? null;
        if ( ! is_string($value) || $value === '') {
            throw new TemplateException(sprintf('%s: "%s" must be a non-empty string.', $file, $key));
        }

        return $value;
    }

    /** @return list<string> */
    private static function list(mixed $value, string $file, string $key): array {
        if ( ! is_array($value)) {
            throw new TemplateException(sprintf('%s: "%s" must be a list of non-empty strings.', $file, $key));
        }

        $strings = [];
        foreach ($value as $item) {
            if ( ! is_string($item) || $item === '') {
                throw new TemplateException(sprintf('%s: "%s" must be a list of non-empty strings.', $file, $key));
            }
            $strings[] = $item;
        }

        return $strings;
    }
}
