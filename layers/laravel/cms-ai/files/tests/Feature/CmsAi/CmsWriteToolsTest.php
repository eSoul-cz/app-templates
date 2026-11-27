<?php

declare(strict_types=1);

namespace Tests\Feature\CmsAi;

use App\Domain\Cms\RevisionSource;
use App\Infrastructure\Models\Page;
use App\Infrastructure\Models\PageRevision;

final class CmsWriteToolsTest extends CmsAiTestCase
{
    public function test_create_page_saves_an_unpublished_page_with_an_ai_revision(): void
    {
        $data = $this->structured($this->callTool($this->token, 'create_page', [
            'title' => 'Services',
            'slug' => 'services',
            'description' => 'What we do',
            'blocks' => [['type' => 'heading', 'data' => ['level' => '2', 'text' => 'Services']]],
        ]));

        $page = Page::query()->findOrFail($data['page_id']);
        $this->assertFalse($page->is_published);
        $this->assertFalse($page->is_home);
        $this->assertFalse($data['published']);
        $this->assertSame('services', $page->slug);

        $revision = PageRevision::query()->findOrFail($data['revision_id']);
        $this->assertSame(RevisionSource::Ai, $revision->source);
        $this->assertSame($this->admin->id, $revision->user_id);
        $this->assertFalse($revision->is_published);
    }

    public function test_create_page_cannot_publish_or_set_the_home_page(): void
    {
        $this->assertToolError($this->callTool($this->token, 'create_page', ['title' => 'X', 'slug' => 'x', 'is_published' => true]), 'is_published');
        $this->assertToolError($this->callTool($this->token, 'create_page', ['title' => 'X', 'slug' => 'x', 'is_home' => true]), 'is_home');
        $this->assertSame(0, Page::query()->count());
    }

    public function test_create_page_with_invalid_input_lists_the_problems_and_stores_nothing(): void
    {
        $response = $this->callTool($this->token, 'create_page', [
            'title' => 'Bad',
            'slug' => 'bad',
            'blocks' => [['type' => 'video', 'data' => []], ['type' => 'heading', 'data' => ['level' => '9']]],
        ]);

        $this->assertToolError($response, 'Block 1: unknown block type "video"');
        $this->assertStringContainsString('Block 2 (heading): the field "level" must be one of: 2, 3', (string) $response->json('result.content.0.text'));
        $this->assertStringContainsString('Block 2 (heading): the field "text" is required', (string) $response->json('result.content.0.text'));
        $this->assertSame(0, Page::query()->count());
        $this->assertSame(0, PageRevision::query()->count());
    }

    public function test_create_page_refuses_a_bad_or_used_slug_and_an_image_that_was_not_uploaded(): void
    {
        $this->makePage(['slug' => 'about']);

        $this->assertToolError($this->callTool($this->token, 'create_page', ['title' => 'X', 'slug' => 'about']), 'already used');
        $this->assertToolError($this->callTool($this->token, 'create_page', ['title' => 'X', 'slug' => 'Bad Slug']), 'slug');
        $this->assertToolError($this->callTool($this->token, 'create_page', [
            'title' => 'X',
            'slug' => 'x',
            'blocks' => [['type' => 'image', 'data' => ['image' => 'images/missing.png', 'alt' => 'x']]],
        ]), 'not an uploaded image');
        $this->assertSame(1, Page::query()->count());
    }

    public function test_update_page_changes_only_what_is_sent_and_records_an_ai_revision(): void
    {
        $page = $this->makePage(['description' => 'Keep me']);

        $data = $this->structured($this->callTool($this->token, 'update_page', ['page_id' => $page->id, 'title' => 'New title']));

        $page->refresh();
        $this->assertTrue($data['changed']);
        $this->assertSame('New title', $page->title);
        $this->assertSame('Keep me', $page->description);
        $this->assertSame(self::BLOCKS, $page->blocks);
        $this->assertSame(RevisionSource::Ai, $page->revisions()->firstOrFail()->source);
        $this->assertSame(2, $page->revisions()->count());
    }

    public function test_update_page_can_clear_the_description_with_null(): void
    {
        $page = $this->makePage(['description' => 'Remove me']);

        $this->structured($this->callTool($this->token, 'update_page', ['page_id' => $page->id, 'description' => null]));

        $this->assertNull($page->refresh()->description);
    }

    public function test_update_page_without_a_change_writes_no_revision(): void
    {
        $page = $this->makePage();

        $data = $this->structured($this->callTool($this->token, 'update_page', ['page_id' => $page->id, 'title' => 'About', 'blocks' => self::BLOCKS]));

        $this->assertFalse($data['changed']);
        $this->assertNull($data['revision_id']);
        $this->assertSame(1, $page->revisions()->count());
    }

    public function test_update_page_cannot_publish_unpublish_or_move_the_home_page(): void
    {
        $page = $this->makePage();

        foreach (['is_published' => true, 'is_home' => true] as $key => $value) {
            $this->assertToolError($this->callTool($this->token, 'update_page', ['page_id' => $page->id, $key => $value]), $key);
        }

        $page->refresh();
        $this->assertFalse($page->is_published);
        $this->assertFalse($page->is_home);
        $this->assertSame(1, $page->revisions()->count());
    }

    public function test_update_page_cannot_change_the_slug_of_a_published_page_but_can_of_a_draft(): void
    {
        $live = $this->makePage(['slug' => 'live', 'is_published' => true]);
        $draft = $this->makePage(['slug' => 'draft', 'title' => 'Draft']);

        $this->assertToolError($this->callTool($this->token, 'update_page', ['page_id' => $live->id, 'slug' => 'moved']), 'published page');
        $this->assertSame('live', $live->refresh()->slug);

        $this->structured($this->callTool($this->token, 'update_page', ['page_id' => $draft->id, 'slug' => 'draft-2']));
        $this->assertSame('draft-2', $draft->refresh()->slug);

        $this->structured($this->callTool($this->token, 'update_page', ['page_id' => $live->id, 'slug' => 'live', 'title' => 'Live now']));
        $this->assertSame('Live now', $live->refresh()->title);
    }

    public function test_update_page_with_invalid_blocks_changes_nothing(): void
    {
        $page = $this->makePage();

        $this->assertToolError($this->callTool($this->token, 'update_page', [
            'page_id' => $page->id,
            'title' => 'Would change',
            'blocks' => [['type' => 'text', 'data' => ['body' => '']]],
        ]), 'the field "body" is required');

        $this->assertSame('About', $page->refresh()->title);
        $this->assertSame(1, $page->revisions()->count());
        $this->assertToolError($this->callTool($this->token, 'update_page', ['page_id' => 999, 'title' => 'x']), 'no page');
        $this->assertToolError($this->callTool($this->token, 'update_page', ['page_id' => $page->id, 'title' => '']), 'title');
    }

    public function test_restore_revision_writes_an_old_version_back_as_a_new_ai_version(): void
    {
        $page = $this->makePage();
        $first = $page->revisions()->firstOrFail();
        $this->structured($this->callTool($this->token, 'update_page', ['page_id' => $page->id, 'title' => 'Changed']));

        $data = $this->structured($this->callTool($this->token, 'restore_revision', ['revision_id' => $first->id]));

        $this->assertTrue($data['changed']);
        $this->assertSame('About', $page->refresh()->title);
        $this->assertSame(3, $page->revisions()->count());
        $this->assertSame(RevisionSource::Ai, $page->revisions()->firstOrFail()->source);
    }

    public function test_restore_revision_refuses_what_would_change_the_published_state_or_a_live_slug(): void
    {
        $page = $this->makePage(['slug' => 'about']);
        $draftVersion = $page->revisions()->firstOrFail();
        $page->forceFill(['is_published' => true])->save();

        $this->assertToolError($this->callTool($this->token, 'restore_revision', ['revision_id' => $draftVersion->id]), 'published state');
        $this->assertTrue($page->refresh()->is_published);
        $this->assertSame(1, $page->revisions()->count());
        $this->assertToolError($this->callTool($this->token, 'restore_revision', ['revision_id' => 999]), 'no version');

        $page->delete();
        $this->assertToolError($this->callTool($this->token, 'restore_revision', ['revision_id' => $draftVersion->id]), 'deleted');
    }

    public function test_restore_revision_refuses_a_version_with_another_noindex_setting(): void
    {
        $page = $this->makePage();
        $first = $page->revisions()->firstOrFail();
        $page->forceFill(['noindex' => true])->save();

        $this->assertToolError($this->callTool($this->token, 'restore_revision', ['revision_id' => $first->id]), 'noindex');
        $this->assertTrue($page->refresh()->noindex);
        $this->assertSame(1, $page->revisions()->count());
    }

    public function test_an_image_path_must_be_an_uploaded_file_not_a_directory(): void
    {
        $page = $this->makePage();

        foreach (['images/', 'images', 'images/../x.png', 'images/abc.png'] as $path) {
            $this->assertToolError($this->callTool($this->token, 'update_page', ['page_id' => $page->id, 'og_image' => $path]), 'og_image');
            $this->assertToolError($this->callTool($this->token, 'update_page', [
                'page_id' => $page->id,
                'blocks' => [['type' => 'image', 'data' => ['image' => $path, 'alt' => 'A']]],
            ]), 'not an uploaded image');
            $this->assertToolError($this->callTool($this->token, 'create_page', ['title' => 'N', 'slug' => 'n', 'og_image' => $path]), 'og_image');
        }
    }
}
