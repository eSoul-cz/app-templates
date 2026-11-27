<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use App\Application\Contract\Cms\PublicMediaStore;
use App\Domain\Cms\InvalidPageBlocks;
use App\Domain\Cms\RevisionSource;
use App\Domain\CQRS\Command\Cms\SavePageCommand;
use App\Domain\CQRS\Query\Cms\ExportContentQuery;
use App\Infrastructure\Filament\Resources\Pages\Pages\CreatePage;
use App\Infrastructure\Filament\Resources\Pages\Pages\EditPage;
use App\Infrastructure\Models\Page;
use App\Infrastructure\Models\PageRevision;
use App\Infrastructure\Models\User;
use Esoul\LaravelCqrs\Facades\CommandBus;
use Esoul\LaravelCqrs\Facades\QueryBus;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\SampleContent;
use Tests\TestCase;

final class PageRevisionTest extends TestCase
{
    private const array BLOCKS = [['type' => 'text', 'data' => ['body' => 'Hello']]];

    public function test_creating_a_page_records_a_revision_with_the_snapshot_and_the_author(): void
    {
        $author = User::factory()->create();

        $saved = CommandBus::dispatch(new SavePageCommand(null, [
            'title' => 'About',
            'slug' => 'about',
            'description' => 'Who we are',
            'noindex' => true,
            'og_image' => null,
            'is_published' => false,
            'blocks' => self::BLOCKS,
        ], RevisionSource::Ai, $author));

        $revision = PageRevision::query()->findOrFail($saved->revisionId);

        self::assertSame($saved->pageId, $revision->page_id);
        self::assertSame(RevisionSource::Ai, $revision->source);
        self::assertSame($author->id, $revision->user_id);
        self::assertSame('About', $revision->title);
        self::assertSame('about', $revision->slug);
        self::assertSame('Who we are', $revision->description);
        self::assertTrue($revision->noindex);
        self::assertFalse($revision->is_published);
        self::assertSame(self::BLOCKS, $revision->blocks);
        self::assertNotNull($revision->created_at);
    }

    public function test_every_update_adds_a_revision_and_only_sent_attributes_change(): void
    {
        $saved = CommandBus::dispatch(new SavePageCommand(null, ['title' => 'About', 'slug' => 'about', 'description' => 'Keep', 'blocks' => self::BLOCKS], RevisionSource::Admin, null));

        // Not sent: description stays. Sent as null: it is cleared.
        CommandBus::dispatch(new SavePageCommand($saved->pageId, ['title' => 'About us'], RevisionSource::Admin, null));
        $page = Page::query()->findOrFail($saved->pageId);
        self::assertSame('Keep', $page->description);
        self::assertSame(self::BLOCKS, $page->blocks);

        CommandBus::dispatch(new SavePageCommand($saved->pageId, ['description' => null], RevisionSource::System, null));
        self::assertNull($page->refresh()->description);
        self::assertSame(3, $page->revisions()->count());
        self::assertSame([RevisionSource::System, RevisionSource::Admin, RevisionSource::Admin], $page->revisions->map(static fn (PageRevision $revision): RevisionSource => $revision->source)->all());
        self::assertSame('Keep', $page->revisions[1]->description, 'the revision keeps the whole snapshot, not only the sent attributes');
    }

    public function test_blocks_outside_the_catalogue_are_refused_and_nothing_is_stored(): void
    {
        $saved = CommandBus::dispatch(new SavePageCommand(null, ['title' => 'About', 'slug' => 'about', 'blocks' => self::BLOCKS], RevisionSource::Admin, null));

        try {
            CommandBus::dispatch(new SavePageCommand($saved->pageId, ['title' => 'Changed', 'blocks' => [['type' => 'script', 'data' => ['src' => 'x.js']]]], RevisionSource::Ai, null));
            self::fail('Expected InvalidPageBlocks.');
        } catch (InvalidPageBlocks $invalid) {
            self::assertSame(['Block 1: unknown block type "script".'], $invalid->problems);
        }

        self::assertSame('About', Page::query()->findOrFail($saved->pageId)->title);
        self::assertSame(1, PageRevision::query()->count());

        $this->expectException(InvalidPageBlocks::class);
        CommandBus::dispatch(new SavePageCommand(null, ['title' => 'New', 'slug' => 'new', 'blocks' => [['type' => 'text', 'data' => ['body' => '']]]], RevisionSource::Ai, null));
    }

    public function test_a_page_that_was_not_created_has_no_revision(): void
    {
        try {
            CommandBus::dispatch(new SavePageCommand(null, ['title' => 'New', 'slug' => 'new', 'blocks' => [['type' => 'video', 'data' => []]]], RevisionSource::Ai, null));
        } catch (InvalidPageBlocks) {
        }

        self::assertSame(0, Page::query()->count());
        self::assertSame(0, PageRevision::query()->count());
    }

    public function test_the_stored_blocks_are_a_plain_list(): void
    {
        $saved = CommandBus::dispatch(new SavePageCommand(null, ['title' => 'About', 'slug' => 'about', 'blocks' => ['9b2c' => self::BLOCKS[0]]], RevisionSource::Admin, null));

        self::assertSame(self::BLOCKS, Page::query()->findOrFail($saved->pageId)->blocks);
    }

    public function test_the_admin_forms_record_revisions_from_the_admin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        Livewire::test(CreatePage::class)
            ->fillForm(['title' => 'Contact', 'slug' => 'contact', 'blocks' => self::BLOCKS])
            ->call('create')
            ->assertHasNoFormErrors();
        $page = Page::query()->where('slug', 'contact')->firstOrFail();

        Livewire::test(EditPage::class, ['record' => $page->getKey()])
            ->fillForm(['title' => 'Contact us'])
            ->call('save')
            ->assertHasNoFormErrors();

        $revisions = $page->revisions()->get();
        self::assertCount(2, $revisions);
        self::assertSame(['Contact us', 'Contact'], $revisions->pluck('title')->all());

        foreach ($revisions as $revision) {
            self::assertSame(RevisionSource::Admin, $revision->source);
            self::assertSame($admin->id, $revision->user_id);
        }
    }

    public function test_a_deleted_page_keeps_its_revisions_and_is_not_exported(): void
    {
        Storage::fake(PublicMediaStore::MEDIA_DISK);
        SampleContent::create();
        $about = Page::query()->where('slug', 'about')->firstOrFail();
        CommandBus::dispatch(new SavePageCommand($about->id, ['title' => 'About us (edited)'], RevisionSource::Admin, null));

        $about->delete();

        self::assertTrue($about->refresh()->trashed());
        self::assertNull(Page::query()->where('slug', 'about')->first());
        self::assertSame(1, PageRevision::query()->where('page_id', $about->id)->count());
        self::assertSame(['home', 'thanks'], array_column(QueryBus::execute(new ExportContentQuery())->payload['pages'], 'slug'));
    }
}
