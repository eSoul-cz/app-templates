<?php

declare(strict_types=1);

namespace ESoul\AppTemplates;

/** A validated addon (see {@see AddonLoader}): an optional capability a project takes on top of its variant. */
final readonly class Addon
{
    public const string ADDON_PLACEHOLDER = '@addon';

    /**
     * @param array<string, list<string>> $variants  supported variant => the addon's layers, in order
     * @param list<string>                $steps     manual steps printed after applying
     * @param string                      $layerRoot directory that holds the layers (`<repo>/layers` or `<addon>/layers`)
     * @param string|null                 $source    URL or path an external addon was loaded from; null for an addon of this repository
     * @param string|null                 $commit    commit of the external addon's repository when it is a git checkout
     */
    public function __construct(
        public string $name,
        public string $description,
        public array $variants,
        public array $steps,
        public string $layerRoot,
        public ?string $source = null,
        public ?string $commit = null,
    ) {
    }

    public function isExternal(): bool {
        return $this->source !== null;
    }

    /** @return list<string> */
    public function layersFor(string $variant): array {
        $layers = $this->variants[$variant] ?? null;
        if ($layers === null) {
            throw new TemplateException(sprintf(
                'Addon "%s" does not support variant "%s". It declares: %s.',
                $this->name,
                $variant,
                implode(', ', array_keys($this->variants)),
            ));
        }

        return $layers;
    }

    public function layerPath(string $layer): string {
        return $this->layerRoot . '/' . $layer;
    }
}
