<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use App\Domain\Cms\PublishStatus;
use App\Domain\Cms\RevisionSource;
use App\Domain\CQRS\Command\Cms\SavePageCommand;
use App\Domain\CQRS\Query\Cms\GetChangesSincePublishQuery;
use App\Infrastructure\Filament\Pages\PublishWebsite;
use App\Infrastructure\Models\Page;
use App\Infrastructure\Models\Publish;
use App\Infrastructure\Models\User;
use Esoul\LaravelCqrs\Facades\CommandBus;
use Esoul\LaravelCqrs\Facades\QueryBus;
use Livewire\Livewire;
use Tests\TestCase;

final class ChangesSincePublishTest extends TestCase
{
    public function test_without_a_publish_every_live_page_is_listed(): void
    {
        $this->save('home', 'Home', RevisionSource::Admin);
        $gone = $this->save('gone', 'Gone', RevisionSource::Admin);
        Page::query()->findOrFail($gone)->delete();

        $changes = QueryBus::execute(new GetChangesSincePublishQuery());

        self::assertSame(['home'], array_map(static fn ($change): string => $change->slug, $changes));
        self::assertTrue($changes[0]->created);
    }

    public function test_only_pages_changed_after_the_last_successful_publish_are_listed_with_their_sources(): void
    {
        $user = User::factory()->create(['name' => 'Jana Nováková']);
        $this->travelTo('2026-11-27 08:00:00');
        $untouched = $this->save('untouched', 'Untouched', RevisionSource::Admin);
        $edited = $this->save('edited', 'Edited', RevisionSource::Admin);
        $removed = $this->save('removed', 'Removed', RevisionSource::Admin);

        $this->travelTo('2026-11-27 09:00:00');
        Publish::query()->create(['status' => PublishStatus::Success, 'started_at' => now(), 'finished_at' => now()->addMinutes(3)]);
        Publish::query()->create(['status' => PublishStatus::Failed, 'started_at' => now()->addMinutes(30), 'finished_at' => now()->addMinutes(31)]);

        $this->travelTo('2026-11-27 10:00:00');
        CommandBus::dispatch(new SavePageCommand($edited, ['title' => 'Edited by a person'], RevisionSource::Admin, $user));
        $this->travelTo('2026-11-27 10:05:00');
        CommandBus::dispatch(new SavePageCommand($edited, ['title' => 'Edited by the system'], RevisionSource::System, null));
        $created = $this->save('fresh', 'Fresh', RevisionSource::Ai);
        $this->travelTo('2026-11-27 10:10:00');
        Page::query()->findOrFail($removed)->delete();

        $changes = collect(QueryBus::execute(new GetChangesSincePublishQuery()))->keyBy('pageId');

        self::assertSame([$removed, $created, $edited], $changes->keys()->all(), 'newest change first, the failed publish does not move the line');
        self::assertFalse($changes->has($untouched));

        self::assertTrue($changes[$removed]->deleted);
        self::assertSame([], $changes[$removed]->sources);

        self::assertTrue($changes[$created]->created);
        self::assertSame([RevisionSource::Ai], $changes[$created]->sources);

        self::assertFalse($changes[$edited]->created);
        self::assertSame([RevisionSource::System, RevisionSource::Admin], $changes[$edited]->sources);
        self::assertSame(2, $changes[$edited]->revisions);
        self::assertNull($changes[$edited]->lastAuthor, 'the newest revision is the assistant, which has no user here');
    }

    public function test_the_publish_page_shows_what_the_assistant_changed(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->travelTo('2026-11-27 08:00:00');
        $this->save('about', 'About', RevisionSource::Admin);
        Publish::query()->create(['status' => PublishStatus::Success, 'started_at' => now()->addHour(), 'finished_at' => now()->addHour()]);
        $this->travelTo('2026-11-27 11:00:00');
        $this->save('pricing', 'Pricing', RevisionSource::Ai);

        Livewire::test(PublishWebsite::class)
            ->assertSee('Changes since the last publish')
            ->assertSee('Pricing (/pricing): created - AI assistant, 1 revision')
            ->assertDontSee('About (/about)');
    }

    public function test_the_publish_page_has_no_changes_section_when_nothing_changed(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->travelTo('2026-11-27 08:00:00');
        $this->save('about', 'About', RevisionSource::Admin);
        $this->travelTo('2026-11-27 09:00:00');
        Publish::query()->create(['status' => PublishStatus::Success, 'started_at' => now(), 'finished_at' => now()]);

        Livewire::test(PublishWebsite::class)->assertDontSee('Changes since the last publish');
    }

    private function save(string $slug, string $title, RevisionSource $source): int
    {
        return CommandBus::dispatch(new SavePageCommand(null, ['title' => $title, 'slug' => $slug], $source, null))->pageId;
    }
}
