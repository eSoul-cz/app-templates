<?php

declare(strict_types=1);

namespace App\Application\Cms;

use App\Domain\Cms\PublishStatus;
use App\Infrastructure\Cms\JenkinsClient;
use App\Models\Publish;
use App\Models\User;
use LogicException;
use Throwable;

/** The Publish button: check the data -> Jenkins builds the site -> status and history. */
final readonly class PublishService
{
    public function __construct(
        private JenkinsClient $jenkins,
        private ContentExporter $exporter,
    ) {}

    /**
     * The publish in progress. The Jenkins callback is best effort, so a publish without a result for longer
     * than `cms.jenkins.timeout_minutes` is marked failed when read; otherwise the Publish button would stay
     * blocked forever.
     */
    public function current(): ?Publish
    {
        Publish::query()
            ->whereIn('status', [PublishStatus::Queued, PublishStatus::Running])
            ->where('started_at', '<', now()->subMinutes($this->timeoutMinutes()))
            ->update([
                'status' => PublishStatus::Failed,
                'message' => 'Timed out: Jenkins reported no result in time.',
                'finished_at' => now(),
            ]);

        return Publish::query()
            ->whereIn('status', [PublishStatus::Queued, PublishStatus::Running])
            ->latest('id')
            ->first();
    }

    public function lastSuccessful(): ?Publish
    {
        return Publish::query()->where('status', PublishStatus::Success)->latest('started_at')->first();
    }

    /** Content changed after the last successful build started (the build reads the export at its start). */
    public function hasUnpublishedChanges(): bool
    {
        $changedAt = ContentChanges::lastChangedAt();

        if ($changedAt === null) {
            return false;
        }

        $started = $this->lastSuccessful()?->started_at;

        return $started === null || $changedAt->greaterThan($started);
    }

    /** @throws PublishBlocked */
    public function publish(User $user): Publish
    {
        if ($this->current() instanceof Publish) {
            throw new PublishBlocked(['A publish is already queued or running.']);
        }

        if ( ! $this->jenkins->isConfigured()) {
            throw new PublishBlocked(['Jenkins is not configured (CMS_JENKINS_JOB_URL, CMS_JENKINS_USER, CMS_JENKINS_API_TOKEN).']);
        }

        $problems = $this->exporter->build()->problems;

        if ($problems !== []) {
            throw new PublishBlocked($problems);
        }

        $publish = Publish::query()->create([
            'user_id' => $user->id,
            'status' => PublishStatus::Queued,
            'started_at' => now(),
        ]);

        try {
            $publish->update(['jenkins_queue_url' => $this->jenkins->triggerBuild(['CMS_PUBLISH_ID' => (string) $publish->id])]);
        } catch (Throwable $exception) {
            report($exception);

            $publish->update([
                'status' => PublishStatus::Failed,
                'message' => $exception->getMessage(),
                'finished_at' => now(),
            ]);
        }

        return $publish;
    }

    /** Manual end of a stuck publish; who ended it is recorded in `message`. */
    public function markFailed(Publish $publish, User $user): Publish
    {
        if ($publish->status->isFinished()) {
            return $publish;
        }

        $publish->update([
            'status' => PublishStatus::Failed,
            'message' => "Marked as failed by {$user->name}.",
            'finished_at' => now(),
        ]);

        return $publish;
    }

    /**
     * Pulls the state from Jenkins when the callback did not arrive. A Jenkins error (e.g. 404 after the queue
     * item was dropped) is thrown so the admin sees it; the publish keeps its state.
     *
     * @throws Throwable
     */
    public function refresh(Publish $publish): Publish
    {
        if ($publish->status->isFinished() || $publish->jenkins_queue_url === null) {
            return $publish;
        }

        $status = $this->jenkins->buildStatus($publish->jenkins_queue_url, $publish->jenkins_build_url);
        $state = PublishStatus::from($status['state']);

        $publish->update([
            'status' => $state,
            'jenkins_build_url' => $status['build_url'] ?? $publish->jenkins_build_url,
            'finished_at' => $state->isFinished() ? now() : null,
        ]);

        return $publish;
    }

    /** Result reported by the `post {}` block of the Jenkins pipeline. */
    public function finishFromCallback(Publish $publish, bool $success, ?string $buildUrl, ?string $contentHash, ?string $message): Publish
    {
        // A late result of a publish already ended by the timeout or by an admin: the real result is recorded
        // with a note. Builds do not overlap (Jenkinsfile `disableConcurrentBuilds()`).
        $late = $publish->status->isFinished();

        $publish->update([
            'status' => $success ? PublishStatus::Success : PublishStatus::Failed,
            'jenkins_build_url' => $buildUrl ?? $publish->jenkins_build_url,
            'content_hash' => $contentHash ?? $publish->content_hash,
            'message' => $late ? trim('Result arrived after the publish had been ended. ' . $message) : $message,
            'finished_at' => now(),
        ]);

        return $publish;
    }

    /**
     * An invalid limit (0, "abc") would end every new publish at once and allow overlapping ones: a
     * configuration error is better than a silent default.
     */
    private function timeoutMinutes(): int
    {
        $minutes = filter_var(config('cms.jenkins.timeout_minutes'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($minutes === false) {
            throw new LogicException('CMS_PUBLISH_TIMEOUT must be a positive integer (minutes).');
        }

        return $minutes;
    }
}
