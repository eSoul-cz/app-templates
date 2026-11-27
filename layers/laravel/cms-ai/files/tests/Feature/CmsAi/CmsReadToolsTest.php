<?php

declare(strict_types=1);

namespace Tests\Feature\CmsAi;

use App\Domain\Cms\RevisionSource;
use App\Domain\CQRS\Command\Cms\SavePageCommand;
use Esoul\LaravelCqrs\Facades\CommandBus;

final class CmsReadToolsTest extends CmsAiTestCase
{
    public function test_list_pages_returns_the_fields_the_assistant_needs(): void
    {
        $page = $this->makePage(['title' => 'About', 'slug' => 'about', 'is_published' => true]);

        $row = $this->structured($this->callTool($this->token, 'list_pages'))['pages'][0];

        $this->assertSame($page->id, $row['id']);
        $this->assertSame('About', $row['title']);
        $this->assertSame('about', $row['slug']);
        $this->assertTrue($row['published']);
        $this->assertNotNull($row['updated_at']);
        $this->assertTrue($row['has_unpublished_changes'], 'no publish yet, so every page has changes');
    }

    public function test_get_page_returns_the_page_with_its_blocks(): void
    {
        $page = $this->makePage(['description' => 'Who we are']);

        $data = $this->structured($this->callTool($this->token, 'get_page', ['page_id' => $page->id]));

        $this->assertSame('Who we are', $data['description']);
        $this->assertSame(self::BLOCKS, $data['blocks']);
        $this->assertFalse($data['published']);
    }

    public function test_get_page_of_an_unknown_page_is_an_error(): void
    {
        $this->assertToolError($this->callTool($this->token, 'get_page', ['page_id' => 999]), 'no page');
        $this->assertToolError($this->callTool($this->token, 'get_page', ['page_id' => 'abc']), 'page id');
    }

    public function test_list_block_types_describes_the_catalogue(): void
    {
        $types = array_column($this->structured($this->callTool($this->token, 'list_block_types'))['block_types'], null, 'type');

        $this->assertEqualsCanonicalizing(['heading', 'text', 'image'], array_keys($types));
        $heading = array_column($types['heading']['fields'], null, 'name');
        $this->assertSame(['2', '3'], $heading['level']['allowed_values']);
        $this->assertTrue($heading['text']['required']);
        $this->assertSame(255, $heading['text']['max_length']);
        $this->assertSame('image', array_column($types['image']['fields'], null, 'name')['image']['type']);
    }

    public function test_list_revisions_names_the_source_and_the_author(): void
    {
        $page = $this->makePage();
        CommandBus::dispatch(new SavePageCommand($page->id, ['title' => 'Changed'], RevisionSource::Ai, $this->admin));

        $revisions = $this->structured($this->callTool($this->token, 'list_revisions', ['page_id' => $page->id]))['revisions'];

        $this->assertSame(['ai', 'admin'], array_column($revisions, 'source'));
        $this->assertSame($this->admin->name, $revisions[0]['author']);
        $this->assertSame(1, $revisions[0]['block_count']);
        $this->assertToolError($this->callTool($this->token, 'list_revisions', ['page_id' => 999]), 'no page');
        $this->assertToolError($this->callTool($this->token, 'list_revisions', ['page_id' => $page->id, 'limit' => 500]), 'limit');
    }
}
