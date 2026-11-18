<?php

declare(strict_types=1);

namespace ESoul\AppTemplates;

/** One item of the `layers` list of a variant. */
final readonly class LayerEntry
{
    /**
     * @param string       $layer   layer name relative to `layers/`, e.g. `laravel/base`
     * @param string       $into    subdirectory of the variant the layer is applied to ('' = root)
     * @param list<string> $exclude globs, relative to the layer's `files/`, `merge/` and `append/`, that are skipped
     */
    public function __construct(
        public string $layer,
        public string $into = '',
        public array $exclude = [],
    ) {
    }
}
