<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Block shapes that the Filament Builder form can express, with the verdict of the block catalogue. The validator
 * test and the parity test over the form both run through them, so the two cannot drift apart unnoticed.
 */
final class BlockShapes
{
    /** @return array<string, array{0: list<array<string, mixed>>, 1: bool}> */
    public static function all(): array
    {
        $image = ['image' => 'images/pixel.png', 'alt' => 'A blue gradient', 'caption' => 'Sample'];

        return [
            'no blocks' => [[], true],
            'heading 2' => [[['type' => 'heading', 'data' => ['level' => '2', 'text' => 'Title']]], true],
            'heading 3 of 255 characters' => [[['type' => 'heading', 'data' => ['level' => '3', 'text' => str_repeat('a', 255)]]], true],
            'heading level 4' => [[['type' => 'heading', 'data' => ['level' => '4', 'text' => 'Title']]], false],
            'heading without text' => [[['type' => 'heading', 'data' => ['level' => '2', 'text' => '']]], false],
            'heading text of 256 characters' => [[['type' => 'heading', 'data' => ['level' => '2', 'text' => str_repeat('a', 256)]]], false],
            'text' => [[['type' => 'text', 'data' => ['body' => "One.\n\nTwo."]]], true],
            'text without body' => [[['type' => 'text', 'data' => ['body' => '']]], false],
            'image' => [[['type' => 'image', 'data' => $image]], true],
            'image without caption' => [[['type' => 'image', 'data' => ['caption' => null] + $image]], true],
            'image without alt' => [[['type' => 'image', 'data' => ['alt' => ''] + $image]], false],
            'image alt of 256 characters' => [[['type' => 'image', 'data' => ['alt' => str_repeat('a', 256)] + $image]], false],
            'image caption of 256 characters' => [[['type' => 'image', 'data' => ['caption' => str_repeat('a', 256)] + $image]], false],
            'image without a file' => [[['type' => 'image', 'data' => ['image' => null] + $image]], false],
            'one bad block among good ones' => [[
                ['type' => 'heading', 'data' => ['level' => '2', 'text' => 'Title']],
                ['type' => 'text', 'data' => ['body' => '']],
            ], false],
        ];
    }
}
