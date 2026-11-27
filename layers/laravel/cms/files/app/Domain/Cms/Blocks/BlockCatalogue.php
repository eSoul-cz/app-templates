<?php

declare(strict_types=1);

namespace App\Domain\Cms\Blocks;

/**
 * The one description of the block types a page may hold. The Filament Builder of the page form is built from
 * it and {@see BlockValidator} checks stored blocks against it, so the two cannot drift apart. A project adds
 * a component of its own in {@see self::standard()}: the block type with its fields. The exporter and the
 * frontend of the content contract must render the new type too.
 */
final readonly class BlockCatalogue
{
    /** @param  list<BlockType>  $types */
    public function __construct(public array $types) {}

    public static function standard(): self
    {
        return new self([
            new BlockType('heading', [
                new BlockField('level', BlockFieldType::Choice, required: true, options: ['2' => 'Heading 2', '3' => 'Heading 3'], default: '2'),
                new BlockField('text', BlockFieldType::Text, required: true, maxLength: 255),
            ], icon: 'OutlinedH1'),
            new BlockType('text', [
                new BlockField('body', BlockFieldType::Textarea, required: true, hint: 'Plain text. A blank line starts a new paragraph.'),
            ], icon: 'OutlinedBars3BottomLeft'),
            new BlockType('image', [
                new BlockField('image', BlockFieldType::Image, required: true),
                new BlockField('alt', BlockFieldType::Text, required: true, maxLength: 255, hint: 'Describe the image for screen readers.'),
                new BlockField('caption', BlockFieldType::Text, maxLength: 255),
            ], icon: 'OutlinedPhoto'),
        ]);
    }

    public function find(string $name): ?BlockType
    {
        foreach ($this->types as $type) {
            if ($type->name === $name) {
                return $type;
            }
        }

        return null;
    }
}
