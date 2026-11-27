<?php

declare(strict_types=1);

namespace App\Domain\Cms\Blocks;

/** A block type of the component catalogue: a name the frontend renders and the fields of its data. */
final readonly class BlockType
{
    /**
     * @param  list<BlockField>  $fields
     * @param  string|null  $icon  name of a Heroicon case of the admin (for example `OutlinedH1`)
     */
    public function __construct(
        public string $name,
        public array $fields,
        public ?string $icon = null,
    ) {}

    public function field(string $name): ?BlockField
    {
        foreach ($this->fields as $field) {
            if ($field->name === $name) {
                return $field;
            }
        }

        return null;
    }
}
