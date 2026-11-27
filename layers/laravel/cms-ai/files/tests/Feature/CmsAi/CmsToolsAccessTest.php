<?php

declare(strict_types=1);

namespace Tests\Feature\CmsAi;

use App\Domain\Providers\CmsAiServiceProvider;
use App\Infrastructure\Models\Page;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;

final class CmsToolsAccessTest extends CmsAiTestCase
{
    private const array READ = ['list_pages', 'get_page', 'list_block_types', 'list_revisions'];

    private const array WRITE = ['create_page', 'update_page', 'upload_image', 'restore_revision'];

    /** @return iterable<string, array{string, array<string, mixed>}> */
    public static function readTools(): iterable
    {
        yield 'list_pages' => ['list_pages', []];
        yield 'get_page' => ['get_page', ['page_id' => 1]];
        yield 'list_block_types' => ['list_block_types', []];
        yield 'list_revisions' => ['list_revisions', ['page_id' => 1]];
    }

    /** @return iterable<string, array{string, array<string, mixed>}> */
    public static function writeTools(): iterable
    {
        yield 'create_page' => ['create_page', ['title' => 'New', 'slug' => 'new']];
        yield 'update_page' => ['update_page', ['page_id' => 1, 'title' => 'Changed']];
        yield 'upload_image' => ['upload_image', ['filename' => 'a.png', 'content_base64' => 'AAAA']];
        yield 'restore_revision' => ['restore_revision', ['revision_id' => 1]];
    }

    public function test_the_connector_lists_the_cms_tools_and_no_tool_for_delete_or_publish(): void
    {
        $tools = $this->mcp($this->token, 'tools/list')->assertOk()->json('result.tools');
        $names = array_column($tools, 'name');

        $this->assertEqualsCanonicalizing(['whoami', ...self::READ, ...self::WRITE], $names);
        $this->assertSame([], preg_grep('/delete|remove|publish|setting|user|submission|home/', $names));
    }

    public function test_tools_carry_mcp_annotations(): void
    {
        $tools = array_column($this->mcp($this->token, 'tools/list')->json('result.tools'), null, 'name');

        foreach (self::READ as $name) {
            $this->assertTrue($tools[$name]['annotations']['readOnlyHint'], $name);
        }

        foreach (self::WRITE as $name) {
            $this->assertFalse($tools[$name]['annotations']['destructiveHint'], $name);
        }

        $this->assertTrue($tools['update_page']['annotations']['idempotentHint']);
        $this->assertTrue($tools['upload_image']['annotations']['idempotentHint']);
    }

    /** @param  array<string, mixed>  $arguments */
    #[DataProvider('readTools')]
    public function test_a_read_tool_needs_the_read_scope(string $tool, array $arguments): void
    {
        $this->makePage();
        $token = $this->connectAssistant($this->admin, ['content:write']);

        $this->assertToolError($this->callTool($token, $tool, $arguments), 'content:read');
    }

    /** @param  array<string, mixed>  $arguments */
    #[DataProvider('writeTools')]
    public function test_a_write_tool_needs_the_write_scope_and_stores_nothing(string $tool, array $arguments): void
    {
        $page = $this->makePage();
        $token = $this->connectAssistant($this->admin, ['content:read']);

        $this->assertToolError($this->callTool($token, $tool, $arguments), 'content:write');
        $this->assertSame(1, Page::query()->count());
        $this->assertSame('About', $page->refresh()->title);
        $this->assertSame(1, $page->revisions()->count());
        $this->assertSame([], Storage::disk('cms-media')->allFiles());
    }

    /** @param  array<string, mixed>  $arguments */
    #[DataProvider('writeTools')]
    public function test_a_write_tool_asks_the_page_policy_and_stores_nothing_when_it_refuses(string $tool, array $arguments): void
    {
        $page = $this->makePage();
        Gate::define(CmsAiServiceProvider::EDIT_PAGES, static fn (): bool => false);

        $this->assertToolError($this->callTool($this->token, $tool, $arguments), 'not allowed');
        $this->assertSame(1, Page::query()->count());
        $this->assertSame('About', $page->refresh()->title);
        $this->assertSame(1, $page->revisions()->count());
    }

    /** @param  array<string, mixed>  $arguments */
    #[DataProvider('readTools')]
    public function test_a_read_tool_asks_the_page_policy(string $tool, array $arguments): void
    {
        $this->makePage();
        Gate::define(CmsAiServiceProvider::VIEW_PAGES, static fn (): bool => false);

        $this->assertToolError($this->callTool($this->token, $tool, $arguments), 'not allowed');
    }

    public function test_a_user_who_lost_admin_access_is_refused_at_once(): void
    {
        $this->admin->forceFill(['is_admin' => false])->save();

        $this->callTool($this->token, 'create_page', ['title' => 'New', 'slug' => 'new'])->assertForbidden();
        $this->assertSame(0, Page::query()->count());
    }
}
