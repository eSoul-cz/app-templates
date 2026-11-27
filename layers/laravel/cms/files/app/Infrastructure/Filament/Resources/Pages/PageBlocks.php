<?php

declare(strict_types=1);

namespace App\Infrastructure\Filament\Resources\Pages;

use App\Application\Contract\Cms\PublicMediaStore;
use App\Domain\Cms\Blocks\BlockCatalogue;
use App\Domain\Cms\Blocks\BlockField;
use App\Domain\Cms\Blocks\BlockFieldType;
use App\Domain\Cms\Blocks\BlockType;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

/** The Builder field of the page form, generated from the block catalogue so that it accepts what the validator accepts. */
final class PageBlocks
{
    public static function builder(?BlockCatalogue $catalogue = null): Builder
    {
        $catalogue ??= resolve(BlockCatalogue::class);

        return Builder::make('blocks')
            ->hiddenLabel()
            ->collapsible()
            ->blockNumbers(false)
            ->addActionLabel('Add block')
            ->blocks(array_map(self::block(...), $catalogue->types));
    }

    private static function block(BlockType $type): Block
    {
        return Block::make($type->name)
            ->icon(self::icon($type->icon))
            ->schema(array_map(self::field(...), $type->fields));
    }

    private static function field(BlockField $field): Field
    {
        $component = match ($field->type) {
            BlockFieldType::Text => TextInput::make($field->name)->maxLength($field->maxLength),
            BlockFieldType::Textarea => Textarea::make($field->name)->rows(6)->maxLength($field->maxLength),
            BlockFieldType::Choice => Select::make($field->name)->options($field->options),
            BlockFieldType::Image => FileUpload::make($field->name)
                ->disk(PublicMediaStore::MEDIA_DISK)
                ->directory('images')
                ->visibility('public')
                ->image()
                ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/avif'])
                ->maxSize(10240),
        };

        return $component
            ->required($field->required)
            ->default($field->default)
            ->helperText($field->hint);
    }

    private static function icon(?string $name): Heroicon
    {
        $case = $name !== null && defined(Heroicon::class . '::' . $name) ? constant(Heroicon::class . '::' . $name) : null;

        return $case instanceof Heroicon ? $case : Heroicon::OutlinedSquares2x2;
    }
}
