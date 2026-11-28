<?php

declare(strict_types=1);

namespace ESoul\AppTemplates;

use stdClass;

/** `.esoul/template.json` of a project: where it came from and which addons it took. */
final class ProjectRecord
{
    public const string PATH = '.esoul/template.json';

    private function __construct(private readonly stdClass $data) {
    }

    /** The record of a fresh build: no addons yet, nothing declined. */
    public static function create(string $variant, string $commit): self {
        $data = new stdClass();
        $data->variant = $variant;
        $data->commit = $commit;
        $data->declined = [];

        return new self($data);
    }

    /** @param string $directory project root */
    public static function read(string $directory): self {
        $file = $directory . '/' . self::PATH;
        if ( ! is_file($file)) {
            throw new TemplateException(sprintf(
                '%s has no %s. It is not a project created from a template, or it has not been adopted yet; update or adopt it first.',
                $directory,
                self::PATH,
            ));
        }

        $data = JsonMerger::decode((string) file_get_contents($file), $file);
        if ( ! $data instanceof stdClass) {
            throw new TemplateException(sprintf('%s must contain a JSON object.', $file));
        }

        return new self($data);
    }

    public function variant(): string {
        $variant = $this->data->variant ?? null;
        if ( ! is_string($variant) || $variant === '') {
            throw new TemplateException(sprintf('%s has no "variant"; the project has to be adopted before it can take an addon.', self::PATH));
        }

        return $variant;
    }

    public function commit(): ?string {
        $commit = $this->data->commit ?? null;

        return is_string($commit) && $commit !== '' ? $commit : null;
    }

    /** Add the addon, or replace the entry of the same name in place. Existing keys keep their order; `addons` is added last. */
    public function withAddon(Addon $addon): self {
        $entry = new stdClass();
        $entry->name = $addon->name;
        if ($addon->source !== null) {
            $entry->source = $addon->source;
            if ($addon->commit !== null) {
                $entry->commit = $addon->commit;
            }
        }

        $addons = $this->data->addons ?? [];
        if ( ! is_array($addons)) {
            throw new TemplateException(sprintf('%s: "addons" must be a list.', self::PATH));
        }

        $replaced = false;
        foreach ($addons as $index => $existing) {
            if ($existing instanceof stdClass && ($existing->name ?? null) === $addon->name) {
                $addons[$index] = $entry;
                $replaced = true;
            }
        }
        if ( ! $replaced) {
            $addons[] = $entry;
        }

        $data = clone $this->data;
        $data->addons = array_values($addons);

        return new self($data);
    }

    public function encode(): string {
        return JsonMerger::encode($this->data);
    }
}
