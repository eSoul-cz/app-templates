<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Cms;

use App\Domain\Cms\Blocks\BlockCatalogue;
use App\Domain\Cms\Blocks\BlockField;
use App\Domain\Cms\Blocks\BlockFieldType;
use App\Domain\Cms\Blocks\BlockType;
use App\Domain\Cms\Blocks\BlockValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\BlockShapes;

final class BlockValidatorTest extends TestCase
{
    /** @return array<string, array{0: list<array<string, mixed>>, 1: bool}> */
    public static function shapes(): array
    {
        return BlockShapes::all();
    }

    /** @param  list<array<string, mixed>>  $blocks */
    #[DataProvider('shapes')]
    public function test_it_accepts_and_rejects_the_shapes_of_the_form(array $blocks, bool $valid): void
    {
        self::assertSame($valid, new BlockValidator(BlockCatalogue::standard())->validate($blocks) === []);
    }

    public function test_null_blocks_and_a_builder_state_keyed_by_uuid_are_valid(): void
    {
        $validator = new BlockValidator(BlockCatalogue::standard());

        self::assertSame([], $validator->validate(null));
        self::assertSame([], $validator->validate(['9b2c' => ['type' => 'text', 'data' => ['body' => 'Hello']]]));
    }

    public function test_an_integer_heading_level_is_valid_like_the_stored_string(): void
    {
        self::assertSame([], new BlockValidator(BlockCatalogue::standard())->validate([['type' => 'heading', 'data' => ['level' => 3, 'text' => 'Title']]]));
    }

    public function test_an_unknown_block_type_is_rejected(): void
    {
        $problems = new BlockValidator(BlockCatalogue::standard())->validate([['type' => 'video', 'data' => []]]);

        self::assertSame(['Block 1: unknown block type "video".'], $problems);
    }

    public function test_an_unknown_field_is_rejected(): void
    {
        $problems = new BlockValidator(BlockCatalogue::standard())->validate([['type' => 'text', 'data' => ['body' => 'Hello', 'html' => '<b>x</b>']]]);

        self::assertSame(['Block 1 (text): unknown field "html".'], $problems);
    }

    public function test_an_unknown_key_of_the_block_is_rejected(): void
    {
        $problems = new BlockValidator(BlockCatalogue::standard())->validate([['type' => 'text', 'data' => ['body' => 'Hello'], 'class' => 'x']]);

        self::assertSame(['Block 1: unknown key class.'], $problems);
    }

    public function test_the_wrong_type_of_a_value_is_rejected(): void
    {
        $validator = new BlockValidator(BlockCatalogue::standard());

        self::assertNotSame([], $validator->validate([['type' => 'text', 'data' => ['body' => ['x']]]]));
        self::assertNotSame([], $validator->validate([['type' => 'heading', 'data' => ['level' => 2.0, 'text' => 'Title']]]));
        self::assertNotSame([], $validator->validate([['type' => 'text']]));
        self::assertNotSame([], $validator->validate(['text']));
        self::assertNotSame([], $validator->validate('text'));
    }

    public function test_a_block_type_added_to_the_catalogue_is_accepted_and_checked(): void
    {
        $catalogue = new BlockCatalogue([
            ...BlockCatalogue::standard()->types,
            new BlockType('quote', [
                new BlockField('quote', BlockFieldType::Textarea, required: true, maxLength: 20),
                new BlockField('author', BlockFieldType::Text),
            ]),
        ]);
        $validator = new BlockValidator($catalogue);

        self::assertSame([], $validator->validate([['type' => 'quote', 'data' => ['quote' => 'Short', 'author' => null]]]));
        self::assertNotSame([], $validator->validate([['type' => 'quote', 'data' => ['quote' => str_repeat('a', 21)]]]));
        self::assertNotSame([], new BlockValidator(BlockCatalogue::standard())->validate([['type' => 'quote', 'data' => ['quote' => 'Short']]]));
    }
}
