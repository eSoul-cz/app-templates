<?php

declare(strict_types=1);

namespace ESoul\AppTemplates;

use stdClass;

/**
 * Applies the layers an addon declares for a variant to a project directory, in the variant's list order. Used by `addon:apply` (an
 * existing project) and by `build --addon` (a fresh build, strict), so both do the same thing.
 *
 * Per layer, relative to the project root: `files/` (new files only), `merge/` (fill only), `append/`, `insert/<slot>/<path>`.
 * Everything is planned against an {@see AddonWorkspace} first; the caller flushes it, so an error never leaves a half-applied addon.
 */
final readonly class AddonApplier
{
    public function __construct(private Catalog $catalog) {
    }

    /**
     * @param bool $strict build mode: a file the addon would create that already exists on disk (it comes from the variant) is an error
     */
    public function apply(Addon $addon, string $variant, AddonWorkspace $workspace, bool $strict): AddonReport {
        $report = new AddonReport();

        foreach ($addon->layersFor($variant) as $layer) {
            $path = $addon->layerPath($layer);
            $where = sprintf('Addon "%s", layer "%s"', $addon->name, $layer);

            $this->files($path . '/files', $workspace, $report, $strict, $where);
            $this->merges($addon, $path . '/merge', $workspace, $report);
            $this->appends($path . '/append', $workspace, $report);
            $this->inserts($addon, $path . '/insert', $workspace, $report, $where);
        }

        return $report;
    }

    private function files(string $dir, AddonWorkspace $workspace, AddonReport $report, bool $strict, string $where): void {
        foreach (LayerFiles::list($dir) as $relative => $source) {
            $content = (string) file_get_contents($source);

            if ($workspace->exists($relative)) {
                if ($strict && $workspace->onDisk($relative)) {
                    throw new TemplateException(sprintf('%s: files/%s already exists in the build. An addon may only add new files.', $where, $relative));
                }
                if ($workspace->read($relative) !== $content) {
                    $report->add(ChangeKind::Kept, $relative, 'differs from the addon\'s file');
                }
                continue;
            }

            $workspace->write($relative, $content, (int) fileperms($source) & 0111);
            $report->add(ChangeKind::Created, $relative);
        }
    }

    private function merges(Addon $addon, string $dir, AddonWorkspace $workspace, AddonReport $report): void {
        foreach (LayerFiles::list($dir) as $relative => $source) {
            $overlay = JsonMerger::decode((string) file_get_contents($source), $source);
            if ($addon->source !== null) {
                $overlay = FillMerger::substitute($overlay, Addon::ADDON_PLACEHOLDER, $addon->source);
            }

            if ( ! $workspace->exists($relative)) {
                $workspace->write($relative, JsonMerger::encode($overlay));
                $report->add(ChangeKind::Created, $relative, 'from merge/');
                if ($relative === 'composer.json') {
                    $report->addPackages(self::packages([['path' => [], 'value' => $overlay]]));
                }
                continue;
            }

            $text = $workspace->read($relative);
            $result = FillMerger::merge(JsonMerger::decode($text, $relative), $overlay);

            foreach ($result->kept as $jsonPath) {
                $report->add(ChangeKind::Kept, $relative, $jsonPath);
            }
            if ( ! $result->changed()) {
                continue;
            }

            $workspace->write($relative, JsonMerger::encode($result->value, JsonMerger::detectIndent($text)));
            $report->add(ChangeKind::Merged, $relative, sprintf('%d added', count($result->added)));
            if ($relative === 'composer.json') {
                $report->addPackages(self::packages($result->added));
            }
        }
    }

    private function appends(string $dir, AddonWorkspace $workspace, AddonReport $report): void {
        foreach (LayerFiles::list($dir) as $relative => $source) {
            $existing = $workspace->exists($relative) ? $workspace->read($relative) : '';
            $added = 0;
            $content = LineAppend::append($existing, (string) file_get_contents($source), $added);

            if ($added > 0) {
                $workspace->write($relative, $content);
                $report->add(ChangeKind::Appended, $relative, sprintf('%d lines', $added));
            }
        }
    }

    private function inserts(Addon $addon, string $dir, AddonWorkspace $workspace, AddonReport $report, string $where): void {
        foreach (LayerFiles::list($dir) as $key => $source) {
            [$slot, $relative] = explode('/', $key, 2);
            $detail = sprintf('slot %s', $slot);

            if ( ! $workspace->exists($relative)) {
                throw $this->slotError($slot, $relative, sprintf('%s does not exist', $relative), $where);
            }

            $content = $workspace->read($relative);
            $lines = SlotInserter::lines($content);
            $slots = SlotInserter::find($lines, $slot);
            if ($slots === []) {
                throw $this->slotError($slot, $relative, sprintf('%s has no line with "%s %s"', $relative, SlotInserter::SLOT_MARKER, $slot), $where);
            }
            if (count($slots) > 1) {
                throw new TemplateException(sprintf(
                    '%s: slot "%s" appears on %d lines of %s (%s); a slot must be on exactly one line.',
                    $where,
                    $slot,
                    count($slots),
                    $relative,
                    implode(', ', array_map(static fn (int $index): string => (string) ($index + 1), $slots)),
                ));
            }

            if (SlotInserter::hasBlock($lines, $slots[0], $addon->name)) {
                $report->add(ChangeKind::Skipped, $relative, $detail . ', block exists');
                continue;
            }

            $eol = str_contains($content, "\r\n") ? "\r\n" : "\n";
            $workspace->write($relative, SlotInserter::insert($lines, $slots[0], $slot, $addon->name, (string) file_get_contents($source), $eol));
            $report->add(ChangeKind::Inserted, $relative, $detail);
        }
    }

    private function slotError(string $slot, string $relative, string $problem, string $where): TemplateException {
        $entry = new ChangelogLookup($this->catalog->root . '/CHANGELOG.md')->entryForSlot($slot);

        return new TemplateException(sprintf(
            '%s: slot "%s" is missing in %s (%s). Update the project to the current template first%s.',
            $where,
            $slot,
            $relative,
            $problem,
            $entry === null ? '' : sprintf('; the changelog entry "%s" adds it', $entry),
        ));
    }

    /**
     * Names of the Composer packages among the added keys of `composer.json`: keys of `require` and `require-dev`, platform requirements
     * (`php`, `ext-*`) left out.
     *
     * @param list<array{path: list<string>, value: mixed}> $added
     *
     * @return list<string>
     */
    private static function packages(array $added): array {
        $names = [];
        foreach ($added as $item) {
            $path = $item['path'];
            $section = $path[0] ?? null;
            if ($path === [] && $item['value'] instanceof stdClass) {
                foreach (['require', 'require-dev'] as $key) {
                    $names = [...$names, ...self::keys($item['value']->{$key} ?? null)];
                }
            } elseif (($section === 'require' || $section === 'require-dev') && count($path) === 1) {
                $names = [...$names, ...self::keys($item['value'])];
            } elseif (($section === 'require' || $section === 'require-dev') && count($path) === 2) {
                $names[] = $path[1];
            }
        }

        return array_values(array_unique(array_filter($names, static fn (string $name): bool => str_contains($name, '/'))));
    }

    /** @return list<string> */
    private static function keys(mixed $value): array {
        return $value instanceof stdClass ? array_map(strval(...), array_keys(get_object_vars($value))) : [];
    }
}
