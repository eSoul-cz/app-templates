<?php

declare(strict_types=1);

namespace Tests\Feature\CmsAi;

use App\Domain\Cms\PageRevisionNotRestorable;
use App\Domain\Cms\RevisionSource;
use App\Domain\Cms\RevisionStatus;
use App\Domain\CQRS\Command\Cms\ApprovePageRevisionCommand;
use App\Domain\CQRS\Command\Cms\DiscardPageRevisionCommand;
use App\Domain\CQRS\Command\Cms\SavePageCommand;
use App\Domain\CQRS\Query\Cms\ExportContentQuery;
use App\Domain\CQRS\Query\Cms\GetChangesSincePublishQuery;
use App\Domain\CQRS\Query\Cms\GetPendingDraftsQuery;
use App\Infrastructure\Models\Page;
use App\Infrastructure\Models\PageRevision;
use App\Infrastructure\Models\User;
use Esoul\LaravelCqrs\Facades\CommandBus;
use Esoul\LaravelCqrs\Facades\QueryBus;

/** The live site changes only when a person acts: an assistant's change of a published page is a pending draft. */
final class CmsPendingDraftTest extends CmsAiTestCase
{
    private const array NEW_BLOCKS = [['type' => 'text', 'data' => ['body' => 'AI text']]];

    public function test_update_page_of_a_published_page_is_stored_as_a_pending_draft_and_the_export_is_unchanged(): void
    {
        $page = $this->publishedPage();

        $data = $this->structured($this->callTool($this->token, 'update_page', ['page_id' => $page->id, 'title' => 'AI title', 'blocks' => self::NEW_BLOCKS]));

        $this->assertTrue($data['pending']);
        $this->assertTrue($data['changed']);
        $this->assertStringContainsString('the live page is unchanged', $data['note']);
        $page->refresh();
        $this->assertSame('About', $page->title);
        $this->assertSame(self::BLOCKS, $page->blocks);
        $this->assertSame([['type' => 'text', 'body' => 'Hello']], $this->exportedBlocks());

        $draft = PageRevision::query()->findOrFail($data['revision_id']);
        $this->assertSame(RevisionStatus::Pending, $draft->status);
        $this->assertSame(RevisionSource::Ai, $draft->source);
        $this->assertTrue($draft->is_published);
        foreach (QueryBus::execute(new GetChangesSincePublishQuery()) as $change) {
            $this->assertNotContains(RevisionSource::Ai, $change->sources);
        }
    }

    public function test_a_second_ai_update_replaces_the_pending_draft_and_continues_from_it(): void
    {
        $page = $this->publishedPage();
        $first = $this->structured($this->callTool($this->token, 'update_page', ['page_id' => $page->id, 'title' => 'First']));
        $second = $this->structured($this->callTool($this->token, 'update_page', ['page_id' => $page->id, 'blocks' => self::NEW_BLOCKS]));

        $this->assertSame($first['revision_id'], $second['revision_id']);
        $this->assertSame(1, PageRevision::query()->where('page_id', $page->id)->where('status', RevisionStatus::Pending)->count());
        $draft = PageRevision::query()->findOrFail($second['revision_id']);
        $this->assertSame('First', $draft->title);
        $this->assertSame(self::NEW_BLOCKS, $draft->blocks);
        $this->assertSame('About', $page->refresh()->title);

        $again = $this->structured($this->callTool($this->token, 'update_page', ['page_id' => $page->id, 'title' => 'First']));
        $this->assertFalse($again['changed']);
    }

    public function test_get_page_and_list_pages_show_the_live_values_and_the_draft(): void
    {
        $page = $this->publishedPage();
        $other = $this->makePage(['slug' => 'other']);
        $this->structured($this->callTool($this->token, 'update_page', ['page_id' => $page->id, 'title' => 'AI title']));

        $got = $this->structured($this->callTool($this->token, 'get_page', ['page_id' => $page->id]));
        $this->assertSame('About', $got['title']);
        $this->assertSame('AI title', $got['draft']['title']);
        $this->assertStringContainsString('draft waiting for approval', $got['draft']['note']);
        $this->assertNull($this->structured($this->callTool($this->token, 'get_page', ['page_id' => $other->id]))['draft']);

        $list = $this->structured($this->callTool($this->token, 'list_pages', []))['pages'];
        $flags = array_column($list, 'has_draft_waiting_for_approval', 'id');
        $this->assertTrue($flags[$page->id]);
        $this->assertFalse($flags[$other->id]);
    }

    public function test_restore_revision_of_a_published_page_creates_a_pending_draft(): void
    {
        $page = $this->publishedPage();
        $original = $page->revisions()->firstOrFail();
        CommandBus::dispatch(new SavePageCommand($page->id, ['title' => 'Admin edit'], RevisionSource::Admin, $this->admin));

        $data = $this->structured($this->callTool($this->token, 'restore_revision', ['revision_id' => $original->id]));

        $this->assertTrue($data['pending']);
        $this->assertSame('Admin edit', $page->refresh()->title);
        $this->assertSame('About', PageRevision::query()->findOrFail($data['revision_id'])->title);
        $this->assertSame(RevisionStatus::Pending, PageRevision::query()->findOrFail($data['revision_id'])->status);
    }

    public function test_an_unpublished_page_is_still_changed_directly(): void
    {
        $page = $this->makePage();

        $data = $this->structured($this->callTool($this->token, 'update_page', ['page_id' => $page->id, 'title' => 'Direct']));

        $this->assertFalse($data['pending']);
        $this->assertSame('Direct', $page->refresh()->title);
        $this->assertSame(RevisionStatus::Applied, PageRevision::query()->findOrFail($data['revision_id'])->status);
    }

    public function test_applying_the_draft_changes_the_page_records_the_approver_and_exports_it(): void
    {
        $page = $this->publishedPage();
        $data = $this->structured($this->callTool($this->token, 'update_page', ['page_id' => $page->id, 'blocks' => self::NEW_BLOCKS]));
        $approver = User::factory()->create(['is_admin' => true]);

        $this->assertCount(1, QueryBus::execute(new GetPendingDraftsQuery()));
        $saved = CommandBus::dispatch(new ApprovePageRevisionCommand($data['revision_id'], $approver));

        $this->assertSame(self::NEW_BLOCKS, $page->refresh()->blocks);
        $this->assertSame([['type' => 'text', 'body' => 'AI text']], $this->exportedBlocks());
        $revision = PageRevision::query()->findOrFail($saved->revisionId);
        $this->assertSame(RevisionSource::Admin, $revision->source);
        $this->assertSame($approver->id, $revision->user_id);
        $this->assertSame(RevisionStatus::Applied, PageRevision::query()->findOrFail($data['revision_id'])->status);
        $this->assertSame([], QueryBus::execute(new GetPendingDraftsQuery()));

        $this->expectException(PageRevisionNotRestorable::class);
        CommandBus::dispatch(new ApprovePageRevisionCommand($data['revision_id'], $approver));
    }

    public function test_applying_does_not_republish_a_page_that_was_unpublished_meanwhile(): void
    {
        $page = $this->publishedPage();
        $data = $this->structured($this->callTool($this->token, 'update_page', ['page_id' => $page->id, 'title' => 'AI title']));
        $page->forceFill(['is_published' => false])->save();

        CommandBus::dispatch(new ApprovePageRevisionCommand($data['revision_id'], $this->admin));

        $this->assertSame('AI title', $page->refresh()->title);
        $this->assertFalse($page->is_published);
    }

    public function test_discarding_the_draft_leaves_the_page_unchanged_and_a_new_ai_change_starts_a_new_draft(): void
    {
        $page = $this->publishedPage();
        $data = $this->structured($this->callTool($this->token, 'update_page', ['page_id' => $page->id, 'title' => 'AI title']));

        CommandBus::dispatch(new DiscardPageRevisionCommand($data['revision_id']));

        $this->assertSame('About', $page->refresh()->title);
        $this->assertSame([['type' => 'text', 'body' => 'Hello']], $this->exportedBlocks());
        $this->assertSame(RevisionStatus::Discarded, PageRevision::query()->findOrFail($data['revision_id'])->status);

        $next = $this->structured($this->callTool($this->token, 'update_page', ['page_id' => $page->id, 'title' => 'Again']));
        $this->assertNotSame($data['revision_id'], $next['revision_id']);
    }

    public function test_an_assistant_cannot_apply_a_draft(): void
    {
        $page = $this->publishedPage();
        $data = $this->structured($this->callTool($this->token, 'update_page', ['page_id' => $page->id, 'title' => 'AI title']));

        $restored = $this->structured($this->callTool($this->token, 'restore_revision', ['revision_id' => $data['revision_id']]));

        $this->assertSame('About', $page->refresh()->title);
        $this->assertTrue($restored['pending'] || ! $restored['changed']);
    }

    /** @return list<array<string, mixed>> */
    private function exportedBlocks(): array
    {
        /** @var list<array<string, mixed>> $pages */
        $pages = QueryBus::execute(new ExportContentQuery())->payload['pages'];

        return $pages[0]['blocks'];
    }

    private function publishedPage(): Page
    {
        return $this->makePage(['is_published' => true, 'is_home' => true]);
    }
}
