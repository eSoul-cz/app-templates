<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use App\Domain\Cms\RevisionSource;
use App\Domain\CQRS\Command\Cms\RestorePageRevisionCommand;
use App\Domain\CQRS\Command\Cms\SavePageCommand;
use App\Infrastructure\Models\Page;
use App\Infrastructure\Models\PageRevision;
use Esoul\LaravelCqrs\Facades\CommandBus;
use Tests\TestCase;

final class SavePageNoChangeTest extends TestCase
{
    private const array ATTRIBUTES = [
        'title' => 'About',
        'slug' => 'about',
        'description' => 'Who we are',
        'is_published' => false,
        'blocks' => [['type' => 'text', 'data' => ['body' => 'Hello']]],
    ];

    public function test_a_save_that_changes_nothing_records_no_revision_for_any_source(): void
    {
        $saved = CommandBus::dispatch(new SavePageCommand(null, self::ATTRIBUTES, RevisionSource::Admin, null));
        $this->assertNotNull($saved->revisionId);

        foreach ([RevisionSource::Admin, RevisionSource::Ai, RevisionSource::System] as $source) {
            $again = CommandBus::dispatch(new SavePageCommand($saved->pageId, self::ATTRIBUTES, $source, null));

            $this->assertSame($saved->pageId, $again->pageId);
            $this->assertNull($again->revisionId);
        }

        $this->assertSame(1, PageRevision::query()->where('page_id', $saved->pageId)->count());
    }

    public function test_a_real_change_still_records_a_revision(): void
    {
        $saved = CommandBus::dispatch(new SavePageCommand(null, self::ATTRIBUTES, RevisionSource::Admin, null));

        $changed = CommandBus::dispatch(new SavePageCommand($saved->pageId, ['title' => 'About us'], RevisionSource::Admin, null));

        $this->assertNotNull($changed->revisionId);
        $this->assertSame(2, PageRevision::query()->where('page_id', $saved->pageId)->count());
    }

    public function test_bringing_back_a_deleted_page_records_a_revision_even_when_the_content_is_the_same(): void
    {
        $saved = CommandBus::dispatch(new SavePageCommand(null, self::ATTRIBUTES, RevisionSource::Admin, null));
        Page::query()->findOrFail($saved->pageId)->delete();

        $restored = CommandBus::dispatch(new RestorePageRevisionCommand($saved->revisionId ?? 0, RevisionSource::Admin, null));

        $this->assertNotNull($restored->revisionId);
        $this->assertNull(Page::query()->findOrFail($saved->pageId)->deleted_at);
        $this->assertSame(2, PageRevision::query()->where('page_id', $saved->pageId)->count());
    }
}
