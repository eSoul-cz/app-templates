<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use App\Application\Cms\PublishBlocked;
use App\Application\Cms\PublishService;
use App\Domain\Cms\PublishStatus;
use App\Infrastructure\Cms\PublicMedia;
use App\Models\Publish;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\SampleContent;
use Tests\TestCase;

final class PublishServiceTest extends TestCase
{
    use RefreshDatabase;

    private const string JOB = 'https://jenkins.example.com/job/site';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cms.jenkins.job_url' => self::JOB,
            'cms.jenkins.user' => 'robot',
            'cms.jenkins.api_token' => 'token',
            'cms.jenkins.timeout_minutes' => 30,
        ]);
        Storage::fake(PublicMedia::MEDIA_DISK);
    }

    public function test_publish_triggers_the_job_with_the_publish_id(): void
    {
        SampleContent::create();
        Http::fake([self::JOB . '/buildWithParameters' => Http::response('', 201, ['Location' => 'http://internal:8080/queue/item/42/'])]);

        $publish = resolve(PublishService::class)->publish(User::factory()->create());

        self::assertSame(PublishStatus::Queued, $publish->status);
        self::assertSame('https://jenkins.example.com/queue/item/42', $publish->jenkins_queue_url);
        Http::assertSent(fn (Request $request): bool => $request->url() === self::JOB . '/buildWithParameters'
            && $request['CMS_PUBLISH_ID'] === (string) $publish->id
            && $request->hasHeader('Authorization'));
    }

    public function test_publish_is_blocked_while_another_one_runs(): void
    {
        SampleContent::create();
        Publish::query()->create(['status' => PublishStatus::Running, 'started_at' => now()]);

        $this->expectException(PublishBlocked::class);

        resolve(PublishService::class)->publish(User::factory()->create());
    }

    public function test_publish_is_blocked_without_jenkins_configuration(): void
    {
        SampleContent::create();
        config(['cms.jenkins.job_url' => null]);

        try {
            resolve(PublishService::class)->publish(User::factory()->create());
            self::fail('Expected PublishBlocked.');
        } catch (PublishBlocked $blocked) {
            self::assertStringContainsString('Jenkins is not configured', $blocked->problems[0]);
        }
    }

    public function test_publish_is_blocked_by_export_problems(): void
    {
        Http::fake();

        try {
            resolve(PublishService::class)->publish(User::factory()->create());
            self::fail('Expected PublishBlocked.');
        } catch (PublishBlocked $blocked) {
            self::assertContains('There is no published page.', $blocked->problems);
        }

        Http::assertNothingSent();
        self::assertSame(0, Publish::query()->count());
    }

    public function test_a_jenkins_failure_marks_the_publish_failed(): void
    {
        SampleContent::create();
        Http::fake([self::JOB . '/buildWithParameters' => Http::response('nope', 500)]);

        $publish = resolve(PublishService::class)->publish(User::factory()->create());

        self::assertSame(PublishStatus::Failed, $publish->status);
        self::assertStringContainsString('HTTP 500', (string) $publish->message);
    }

    public function test_a_publish_without_a_result_times_out(): void
    {
        $stale = Publish::query()->create(['status' => PublishStatus::Running, 'started_at' => now()->subMinutes(31)]);

        self::assertNull(resolve(PublishService::class)->current());
        self::assertSame(PublishStatus::Failed, $stale->refresh()->status);
    }

    public function test_refresh_polls_the_queue_and_then_the_build(): void
    {
        $queue = 'https://jenkins.example.com/queue/item/42';
        $build = 'https://jenkins.example.com/job/site/7/';
        $publish = Publish::query()->create(['status' => PublishStatus::Queued, 'started_at' => now(), 'jenkins_queue_url' => $queue]);
        $service = resolve(PublishService::class);

        Http::fake([
            $queue . '/api/json' => Http::sequence()->push(['executable' => null])->push(['executable' => ['url' => $build]]),
            rtrim($build, '/') . '/api/json' => Http::sequence()->push(['building' => true, 'result' => null])->push(['building' => false, 'result' => 'SUCCESS']),
        ]);

        self::assertSame(PublishStatus::Queued, $service->refresh($publish)->status);

        $publish = $service->refresh($publish);
        self::assertSame(PublishStatus::Running, $publish->status);
        self::assertSame($build, $publish->jenkins_build_url);

        $publish = $service->refresh($publish);
        self::assertSame(PublishStatus::Success, $publish->status);
        self::assertNotNull($publish->finished_at);
    }

    public function test_refresh_rejects_an_unexpected_jenkins_answer(): void
    {
        $queue = 'https://jenkins.example.com/queue/item/42';
        $publish = Publish::query()->create(['status' => PublishStatus::Queued, 'started_at' => now(), 'jenkins_queue_url' => $queue]);
        Http::fake([$queue . '/api/json' => Http::response('<html>login</html>')]);

        $this->expectExceptionMessage('Unexpected Jenkins response');

        resolve(PublishService::class)->refresh($publish);
    }

    public function test_unpublished_changes_are_detected(): void
    {
        SampleContent::create();
        $service = resolve(PublishService::class);

        self::assertTrue($service->hasUnpublishedChanges());

        $this->travelTo(now()->addMinute());
        Publish::query()->create(['status' => PublishStatus::Success, 'started_at' => now(), 'finished_at' => now()]);

        self::assertFalse($service->hasUnpublishedChanges());
    }
}
