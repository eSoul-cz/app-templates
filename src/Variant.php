<?php

declare(strict_types=1);

namespace ESoul\AppTemplates;

final readonly class Variant
{
    /** @param list<LayerEntry> $layers */
    public function __construct(
        public string $name,
        public string $package,
        public string $description,
        public array $layers,
    ) {
    }
}
