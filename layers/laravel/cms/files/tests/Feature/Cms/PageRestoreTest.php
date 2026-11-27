<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use App\Domain\Cms\InvalidPageBlocks;
use App\Domain\Cms\PageRevisionNotRestorable;
use App\Domain\Cms\RevisionSource;
use App\Domain\CQRS\Command\Cms\RestorePageRevisionCommand;
use App\Domain\CQRS\Command\Cms\SavePageCommand;
use App\Infrastructure\Filament\Resources\Pages\Pages\EditPage;
use App\Infrastructure\Filament\Resources\Pages\RelationManagers\RevisionsRelationManager;
use App\Infrastructure\Models\Page;
use App\Infrastructure\Models\PageRevision;
use App\Infrastructure\Models\User;
use Esoul\LaravelCqrs\Facades\CommandBus;
use Livewire\Livewire;
use Tests\TestCase;

final class PageRestoreTest extends TestCase
{
    private const array OLD_BLOCKS = [['type' => 'text', 'data' => ['body' => 'Old text']]];

    private const array NEW_BLOCKS = [['type' => 'heading', 'data' => ['level' => '2', 'text' => 'New']]];

    public function test_a_revision_is_restored_in_one_step_and_the_restore_is_a_new_revision(): void
    {
        $author = User::factory()->create();
        $page = $this->pageWithTwoVersions();
        $first = PageRevision::query()->where('page_id', $page->id)->orderBy('id')->firstOrFail();

        CommandBus::dispatch(new RestorePageRevisionCommand($first->id, RevisionSource::Admin, $author));

        $page->refresh();
        self::assertSame('Old title', $page->title);
        self::assertSame('old-slug', $page->slug);
        self::assertSame('Old description', $page->description);
        self::assertSame(self::OLD_BLOCKS, $page->blocks);
        self::assertSame(3, $page->revisions()->count());

        $latest = $page->revisions()->firstOrFail();
        self::assertSame(RevisionSource::Admin, $latest->source);
        self::assertSame($author->id, $latest->user_id);
        self::assertSame('Old title', $latest->title);
        self::assertSame(2, PageRevision::query()->where('page_id', $page->id)->where('title', 'New title')->count() + 1, 'the revision that was replaced stays in the history');
    }

    public function test_a_deleted_page_comes_back_with_its_revision(): void
    {
        $page = $this->pageWithTwoVersions();
        $first = PageRevision::query()->where('page_id', $page->id)->orderBy('id')->firstOrFail();
        $page->delete();

        CommandBus::dispatch(new RestorePageRevisionCommand($first->id, RevisionSource::Admin, null));

        self::assertFalse($page->refresh()->trashed());
        self::assertSame('Old title', $page->title);
    }

    public function test_a_revision_is_not_restored_when_another_page_took_its_slug(): void
    {
        $page = $this->pageWithTwoVersions();
        $first = PageRevision::query()->where('page_id', $page->id)->orderBy('id')->firstOrFail();
        CommandBus::dispatch(new SavePageCommand(null, ['title' => 'Other', 'slug' => 'old-slug'], RevisionSource::Admin, null));

        try {
            CommandBus::dispatch(new RestorePageRevisionCommand($first->id, RevisionSource::Admin, null));
            self::fail('Expected PageRevisionNotRestorable.');
        } catch (PageRevisionNotRestorable) {
            self::assertSame('New title', $page->refresh()->title);
            self::assertSame(2, $page->revisions()->count());
        }
    }

    public function test_a_revision_that_no_longer_fits_the_catalogue_is_not_restored(): void
    {
        $page = $this->pageWithTwoVersions();
        // A revision written before the catalogue changed (or by a bug): inserted directly.
        $stale = PageRevision::query()->create([
            'page_id' => $page->id,
            'title' => 'Stale',
            'slug' => 'stale',
            'blocks' => [['type' => 'video', 'data' => ['url' => 'x']]],
            'source' => RevisionSource::System,
        ]);

        $this->expectException(InvalidPageBlocks::class);

        try {
            CommandBus::dispatch(new RestorePageRevisionCommand($stale->id, RevisionSource::Admin, null));
        } finally {
            self::assertSame('New title', $page->refresh()->title);
            self::assertSame(3, $page->revisions()->count());
        }
    }

    public function test_the_history_lists_the_revisions_and_restores_one_from_the_page(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $page = $this->pageWithTwoVersions();
        $first = PageRevision::query()->where('page_id', $page->id)->orderBy('id')->firstOrFail();
        $second = PageRevision::query()->where('page_id', $page->id)->orderByDesc('id')->firstOrFail();

        Livewire::test(RevisionsRelationManager::class, ['ownerRecord' => $page, 'pageClass' => EditPage::class])
            ->assertCanSeeTableRecords([$first, $second])
            ->callTableAction('restore', $first)
            ->assertNotified('The revision was restored');

        self::assertSame('Old title', $page->refresh()->title);
        self::assertSame(RevisionSource::Admin, $page->revisions()->firstOrFail()->source);
        Livewire::test(EditPage::class, ['record' => $page->getKey()])->assertSeeLivewire(RevisionsRelationManager::class);
    }

    private function pageWithTwoVersions(): Page
    {
        $saved = CommandBus::dispatch(new SavePageCommand(null, [
            'title' => 'Old title',
            'slug' => 'old-slug',
            'description' => 'Old description',
            'blocks' => self::OLD_BLOCKS,
        ], RevisionSource::Admin, null));
        CommandBus::dispatch(new SavePageCommand($saved->pageId, [
            'title' => 'New title',
            'slug' => 'new-slug',
            'description' => 'New description',
            'blocks' => self::NEW_BLOCKS,
        ], RevisionSource::Admin, null));

        return Page::query()->findOrFail($saved->pageId);
    }
}
