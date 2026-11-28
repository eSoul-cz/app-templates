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
 * Afterwards the root `composer.json` is stamped with the variant's package name, then the requested addons are applied (see
 * {@see AddonApplier}, strict: they may only add), the guidelines are rendered and the record is written.
 */
final readonly class Builder
{
    /** Project config of the esoul-cz/guidelines installer; one per variant, at its root. */
    public const string GUIDELINES_CONFIG = 'esoul-guidelines.json';

    public const string POST_CREATE_EVENT = 'post-create-project-cmd';

    /**
     * Run first on `composer create-project`: the project is no longer the MIT template, so its licence becomes proprietary and the
     * template's MIT text is kept only as attribution. Plain PHP and Composer, no shell builtins, so it works on every OS.
     */
    public const array POST_CREATE_COMMANDS = [
        '@php -r "is_dir(\'.esoul\') || mkdir(\'.esoul\', 0755, true); is_file(\'LICENSE\') && rename(\'LICENSE\', \'.esoul/template-LICENSE\');"',
        '@composer config license proprietary',
    ];

    private Filesystem $fs;

    private AddonApplier $addons;

    /** @param GuidelinesRenderer|null $guidelines renders the project guidelines after layering; null skips it (builder tests only) */
    public function __construct(private Catalog $catalog, private ?GuidelinesRenderer $guidelines = null) {
        $this->fs = new Filesystem();
        $this->addons = new AddonApplier($catalog);
    }

    /**
     * Build one variant into `<outDir>/<variant name>` (cleaned first) and return that path.
     *
     * @param string|null $commit app-templates source commit the variant is built from; when set,
     *                            `.esoul/template.json` {variant, commit, declined: []} is written into the result
     * @param list<Addon> $addons addons applied on top of the variant (each must declare it); they are recorded in `.esoul/template.json`,
     *                            so a source commit is required
     */
    public function build(string $variantName, string $outDir, ?string $commit = null, array $addons = []): string {
        $variant = $this->catalog->variant($variantName);
        if ($addons !== [] && $commit === null) {
            throw new TemplateException('A build with addons records them in .esoul/template.json, which needs a source commit: pass --commit or build from a git checkout.');
        }
        foreach ($addons as $addon) {
            $addon->layersFor($variant->name);
        }

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

        $this->stampComposer($variant, $target, $this->nestedComposerDirs($variant, $target));

        $record = $commit === null ? null : ProjectRecord::create($variant->name, $commit);
        foreach ($addons as $addon) {
            $workspace = new AddonWorkspace($target);
            $this->addons->apply($addon, $variant->name, $workspace, true);
            $workspace->flush();
            $record = $record?->withAddon($addon);
        }

        // After the addons, so the guidelines include the layers they merged into esoul-guidelines.json.
        $this->guidelines?->render($target);

        if ($record !== null) {
            $this->fs->dumpFile($target . '/' . ProjectRecord::PATH, $record->encode());
        }

        return $target;
    }

    /** @param list<string> $exclude */
    private function applyLayer(string $layerPath, string $target, array $exclude, string $root): void {
        $this->applyDeletes($layerPath, $target);

        foreach (LayerFiles::list($layerPath . '/files', $exclude) as $relative => $source) {
            $this->fs->copy($source, $target . '/' . $relative, true);
        }

        foreach (LayerFiles::list($layerPath . '/merge', $exclude) as $relative => $source) {
            if ( ! str_ends_with($relative, '.json')) {
                throw new TemplateException(sprintf('Only .json files can be merged, found %s.', $source));
            }
            $destination = $relative === self::GUIDELINES_CONFIG ? $root : $target;
            $this->mergeJson($source, $destination . '/' . $relative);
        }

        foreach (LayerFiles::list($layerPath . '/append', $exclude) as $relative => $source) {
            $this->appendLines($source, $target . '/' . $relative);
        }
    }

    private function applyDeletes(string $layerPath, string $target): void {
        $file = $layerPath . '/delete.txt';
        if ( ! is_file($file)) {
            return;
        }

        $patterns = [];
        foreach (LineAppend::splitLines((string) file_get_contents($file)) as $line) {
            $line = trim($line);
            if ($line !== '' && ! str_starts_with($line, '#')) {
                $patterns[] = $line;
            }
        }
        if ($patterns === []) {
            return;
        }

        $doomed = [];
        $finder = new Finder()->in($target)->ignoreDotFiles(false)->ignoreVCS(false)->ignoreUnreadableDirs();
        foreach ($finder as $item) {
            $relative = str_replace('\\', '/', $item->getRelativePathname());
            if (Glob::matchesAny($patterns, $relative)) {
                $doomed[] = $item->getPathname();
            }
        }

        // Directories are removed recursively; entries already gone with their parent are ignored by remove().
        $this->fs->remove($doomed);
    }

    private function mergeJson(string $source, string $destination): void {
        $overlay = JsonMerger::decode((string) file_get_contents($source), $source);

        if (is_file($destination)) {
            $base = JsonMerger::decode((string) file_get_contents($destination), $destination);
            $overlay = JsonMerger::merge($base, $overlay);
        }

        $this->fs->dumpFile($destination, JsonMerger::encode($overlay));
    }

    private function appendLines(string $source, string $destination): void {
        $existing = is_file($destination) ? (string) file_get_contents($destination) : '';

        $this->fs->dumpFile($destination, LineAppend::append($existing, (string) file_get_contents($source)));
    }

    /**
     * Make the root composer.json carry the variant's package name and the MIT licence the variant
     * repositories are published under (created when no layer ships one; `common` ships LICENSE), and
     * prepend the `post-create-project-cmd` steps that hand the licence over to the new project:
     * `LICENSE` moves to `.esoul/template-LICENSE` (attribution) and `license` becomes `proprietary`
     * (also in the `composer.json` of every `into` subdirectory, e.g. a monorepo's `backend/`).
     *
     * @param list<string> $nestedDirs subdirectories of the variant that carry their own composer.json
     */
    private function stampComposer(Variant $variant, string $target, array $nestedDirs): void {
        $file = $target . '/composer.json';
        $composer = is_file($file) ? JsonMerger::decode((string) file_get_contents($file), $file) : new stdClass();
        if ( ! $composer instanceof stdClass) {
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
        $this->stampPostCreateCommands($stamped, $nestedDirs);

        $this->fs->dumpFile($file, JsonMerger::encode($stamped));
    }

    /** @return list<string> distinct `into` directories (sorted) that have a composer.json after layering */
    private function nestedComposerDirs(Variant $variant, string $target): array {
        $dirs = [];
        foreach ($variant->layers as $entry) {
            if ($entry->into !== '' && is_file($target . '/' . $entry->into . '/composer.json')) {
                $dirs[$entry->into] = true;
            }
        }
        $dirs = array_keys($dirs);
        sort($dirs, SORT_STRING);

        return $dirs;
    }

    /**
     * Prepend {@see POST_CREATE_COMMANDS} (and the licence step of each nested composer.json) to `scripts.post-create-project-cmd`,
     * keeping existing steps (e.g. Laravel's key:generate).
     *
     * @param list<string> $nestedDirs
     */
    private function stampPostCreateCommands(stdClass $composer, array $nestedDirs): void {
        $scripts = $composer->scripts ?? new stdClass();
        if ( ! $scripts instanceof stdClass) {
            throw new TemplateException('composer.json "scripts" must be an object.');
        }

        $current = $scripts->{self::POST_CREATE_EVENT} ?? [];
        $existing = [];
        foreach (is_array($current) ? $current : [$current] as $command) {
            if ( ! is_string($command)) {
                throw new TemplateException(sprintf('composer.json "scripts.%s" must be a string or a list of strings.', self::POST_CREATE_EVENT));
            }
            $existing[] = $command;
        }

        $scripts->{self::POST_CREATE_EVENT} = array_values(array_unique([
            ...self::POST_CREATE_COMMANDS,
            ...array_map(static fn (string $dir): string => sprintf('@composer config --working-dir=%s license proprietary', $dir), $nestedDirs),
            ...$existing,
        ]));
        $composer->scripts = $scripts;
    }
}
