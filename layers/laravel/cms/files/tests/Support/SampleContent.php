<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Infrastructure\Cms\PublicMedia;
use App\Models\Page;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Storage;

/** Deterministic content for the golden export and the endpoint tests. Needs `Storage::fake(PublicMedia::MEDIA_DISK)`. */
final class SampleContent
{
    public static function create(): void
    {
        Storage::disk(PublicMedia::MEDIA_DISK)->put('images/pixel.png', (string) file_get_contents(__DIR__ . '/../Fixtures/pixel.png'));

        SiteSetting::query()->create([
            'site_name' => 'Example Site',
            'description' => 'A sample site built from the CMS export.',
            'contact_email' => 'hello@example.com',
            'footer_text' => 'Example Site s.r.o.',
            'og_image' => 'images/pixel.png',
        ]);

        Page::query()->create([
            'slug' => 'home',
            'title' => 'Welcome',
            'description' => 'The home page.',
            'is_home' => true,
            'sort' => 10,
            'blocks' => [
                ['type' => 'heading', 'data' => ['level' => '2', 'text' => 'What we do']],
                ['type' => 'text', 'data' => ['body' => "First paragraph.\n\nSecond paragraph."]],
                ['type' => 'image', 'data' => ['image' => 'images/pixel.png', 'alt' => 'A blue gradient', 'caption' => 'A tiny sample image']],
            ],
        ]);

        Page::query()->create([
            'slug' => 'about',
            'title' => 'About us',
            'sort' => 20,
            'blocks' => [
                ['type' => 'heading', 'data' => ['level' => '3', 'text' => 'Our story']],
                ['type' => 'text', 'data' => ['body' => 'We write small, boring, reliable software.']],
            ],
        ]);

        // Not exported: unpublished.
        Page::query()->create(['slug' => 'draft', 'title' => 'Draft', 'is_published' => false, 'sort' => 30, 'blocks' => []]);
    }
}
