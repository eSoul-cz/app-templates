<?php

declare(strict_types=1);

namespace Tests\Feature\CmsAi;

use App\Application\Contract\Cms\PublicMediaStore;
use App\Domain\Cms\RevisionSource;
use App\Domain\CQRS\Command\Cms\SavePageCommand;
use App\Infrastructure\Models\Page;
use App\Infrastructure\Models\User;
use Esoul\LaravelCqrs\Facades\CommandBus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Feature\AiConnector\AiConnectorTestCase;

/** An admin with a connected assistant (read and write) and a fake media disk. */
abstract class CmsAiTestCase extends AiConnectorTestCase
{
    protected const array BLOCKS = [['type' => 'text', 'data' => ['body' => 'Hello']]];

    protected User $admin;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(PublicMediaStore::MEDIA_DISK);
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->token = $this->connectAssistant($this->admin);
    }

    /** @param  array<string, mixed>  $attributes */
    protected function makePage(array $attributes = []): Page
    {
        $saved = CommandBus::dispatch(new SavePageCommand(null, [
            'title' => 'About',
            'slug' => 'about',
            'is_published' => false,
            'blocks' => self::BLOCKS,
            ...$attributes,
        ], RevisionSource::Admin, $this->admin));

        return Page::query()->findOrFail($saved->pageId);
    }

    /** @return array<string, mixed> */
    protected function structured(TestResponse $response): array
    {
        $response->assertOk();
        self::assertNotTrue($response->json('result.isError'), (string) $response->json('result.content.0.text'));

        return $response->json('result.structuredContent');
    }

    protected function assertToolError(TestResponse $response, string $contains): void
    {
        $response->assertOk();
        self::assertTrue($response->json('result.isError'), 'The tool should have been refused.');
        self::assertStringContainsString($contains, (string) $response->json('result.content.0.text'));
    }

    protected function pixel(): string
    {
        return (string) file_get_contents(__DIR__ . '/../../Fixtures/pixel.png');
    }
}
