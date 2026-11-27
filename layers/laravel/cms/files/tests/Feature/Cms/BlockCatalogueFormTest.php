<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use App\Application\Contract\Cms\PublicMediaStore;
use App\Domain\Cms\Blocks\BlockCatalogue;
use App\Domain\Cms\Blocks\BlockField;
use App\Domain\Cms\Blocks\BlockType;
use App\Domain\Cms\Blocks\BlockValidator;
use App\Infrastructure\Filament\Resources\Pages\Pages\CreatePage;
use App\Infrastructure\Models\Page;
use App\Infrastructure\Models\User;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Field;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BlockShapes;
use Tests\TestCase;

/** The Filament Builder is generated from the block catalogue; these tests prove that it and the validator agree. */
final class BlockCatalogueFormTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(PublicMediaStore::MEDIA_DISK);
        Storage::disk(PublicMediaStore::MEDIA_DISK)->put('images/pixel.png', (string) file_get_contents(__DIR__ . '/../../Fixtures/pixel.png'));
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    /** @return array<string, array{0: list<array<string, mixed>>, 1: bool}> */
    public static function shapes(): array
    {
        return BlockShapes::all();
    }

    public function test_the_builder_has_one_block_per_catalogue_type_with_its_fields(): void
    {
        $catalogue = resolve(BlockCatalogue::class);
        $page = Livewire::test(CreatePage::class)->instance();
        $builder = collect($page->form->getFlatComponents())->first(static fn (mixed $component): bool => $component instanceof Builder);

        self::assertInstanceOf(Builder::class, $builder);
        $blocks = array_values($builder->getBlocks());
        self::assertSame(array_map(static fn (BlockType $type): string => $type->name, $catalogue->types), array_map(static fn (Block $block): ?string => $block->getName(), $blocks));

        foreach ($catalogue->types as $index => $type) {
            $fields = array_values($blocks[$index]->getDefaultChildComponents());

            self::assertSame(array_map(static fn (BlockField $field): string => $field->name, $type->fields), array_map(static fn (Field $field): ?string => $field->getName(), $fields));

            foreach ($type->fields as $position => $field) {
                self::assertSame($field->required, $fields[$position]->isRequired(), "{$type->name}.{$field->name} required");
            }
        }
    }

    /** @param  list<array<string, mixed>>  $blocks */
    #[DataProvider('shapes')]
    public function test_the_form_and_the_validator_agree(array $blocks, bool $valid): void
    {
        self::assertSame($valid, resolve(BlockValidator::class)->validate($blocks) === []);

        $form = Livewire::test(CreatePage::class)
            ->fillForm(['title' => 'Shape', 'slug' => 'shape', 'blocks' => $this->asFormState($blocks)])
            ->call('create');

        if ($valid) {
            $form->assertHasNoFormErrors();
            self::assertSame(1, Page::query()->count());
        } else {
            self::assertTrue($form->errors()->isNotEmpty(), 'The form accepted a shape the catalogue rejects.');
            self::assertSame(0, Page::query()->count());
        }
    }

    /**
     * The form holds the file of an upload field as a list; the stored data is the plain path.
     *
     * @param  list<array<string, mixed>>  $blocks
     * @return list<array<string, mixed>>
     */
    private function asFormState(array $blocks): array
    {
        foreach ($blocks as &$block) {
            if ($block['type'] === 'image') {
                $block['data']['image'] = is_string($block['data']['image']) ? [$block['data']['image']] : [];
            }
        }

        return $blocks;
    }
}
