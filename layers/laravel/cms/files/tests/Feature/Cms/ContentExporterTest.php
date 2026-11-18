<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use App\Application\Cms\ContentExporter;
use App\Infrastructure\Cms\PublicMedia;
use App\Models\Page;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\SampleContent;
use Tests\TestCase;

final class ContentExporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_empty_site_cannot_be_exported(): void
    {
        $problems = resolve(ContentExporter::class)->build()->problems;

        self::assertContains('There is no published page.', $problems);
        self::assertContains('Exactly one published page must be the home page.', $problems);
    }

    public function test_only_published_pages_are_exported_in_order(): void
    {
        Storage::fake(PublicMedia::MEDIA_DISK);
        SampleContent::create();

        $pages = resolve(ContentExporter::class)->build()->payload['pages'];

        self::assertIsArray($pages);
        self::assertSame(['home', 'about'], array_column($pages, 'slug'));
    }

    public function test_marking_a_page_as_home_unsets_the_previous_home(): void
    {
        Storage::fake(PublicMedia::MEDIA_DISK);
        SampleContent::create();

        Page::query()->where('slug', 'about')->firstOrFail()->update(['is_home' => true]);

        self::assertSame(['about'], Page::query()->where('is_home', true)->pluck('slug')->all());
    }

    public function test_every_contract_violation_is_reported(): void
    {
        Storage::fake(PublicMedia::MEDIA_DISK);
        Storage::disk(PublicMedia::MEDIA_DISK)->put('images/page.html', '<script>alert(1)</script>');

        SiteSetting::query()->create(['site_name' => 'Example', 'contact_email' => 'not-an-email', 'og_image' => 'images/missing.png']);
        Page::query()->create([
            'slug' => 'Bad Slug',
            'title' => 'Broken',
            'is_home' => true,
            'blocks' => [
                ['type' => 'heading', 'data' => ['level' => '2', 'text' => '']],
                ['type' => 'image', 'data' => ['image' => 'images/page.html', 'alt' => '']],
                ['type' => 'carousel', 'data' => []],
            ],
        ]);

        $problems = implode("\n", resolve(ContentExporter::class)->build()->problems);

        foreach (['contact e-mail', 'images/missing.png does not exist', 'single hyphens', 'heading is empty', 'file type text/', 'no alt text', 'unknown block type'] as $expected) {
            self::assertStringContainsString($expected, $problems);
        }
    }
}
