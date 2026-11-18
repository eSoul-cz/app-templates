<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use App\Domain\Cms\PublishStatus;
use App\Infrastructure\Cms\PublicMedia;
use App\Models\Publish;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class PrunePublicMediaTest extends TestCase
{
    public function test_it_deletes_only_files_unused_since_the_anchor_publish(): void
    {
        $disk = Storage::fake(PublicMedia::DISK);
        $disk->put('old.png', 'a');
        $disk->put('fresh.png', 'b');
        touch($disk->path('old.png'), now()->subDays(20)->getTimestamp());

        Publish::query()->create(['status' => PublishStatus::Success, 'started_at' => now()->subDays(10), 'finished_at' => now()->subDays(10)]);

        $this->artisan('cms:prune-public-media')->assertSuccessful();

        $disk->assertMissing('old.png');
        $disk->assertExists('fresh.png');
    }

    public function test_without_an_old_successful_publish_nothing_is_deleted(): void
    {
        $disk = Storage::fake(PublicMedia::DISK);
        $disk->put('old.png', 'a');
        touch($disk->path('old.png'), now()->subDays(20)->getTimestamp());

        $this->artisan('cms:prune-public-media')->assertSuccessful();

        $disk->assertExists('old.png');
    }

    public function test_the_media_name_is_content_addressed_and_only_images_are_served(): void
    {
        $hash = hash('sha256', 'x');

        self::assertSame(substr($hash, 0, 20) . '.png', PublicMedia::name($hash, 'image/png'));
        self::assertNull(PublicMedia::name($hash, 'text/html'));
        self::assertNull(PublicMedia::name('short', 'image/png'));
    }
}
