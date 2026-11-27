<?php

declare(strict_types=1);

namespace App\Domain\CQRS\Query\Cms;

use App\Application\Dto\Cms\PageChange;
use App\Domain\Cms\RevisionSource;
use App\Domain\CQRS\Cms\CurrentPublish;
use App\Infrastructure\Models\Page;
use App\Infrastructure\Models\PageRevision;
use Esoul\Cqrs\Attributes\HandlesQuery;
use Esoul\Cqrs\Contracts\QueryHandlerInterface;
use Esoul\Cqrs\Contracts\QueryInterface;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

#[HandlesQuery(GetChangesSincePublishQuery::class)]
final readonly class GetChangesSincePublishQueryHandler implements QueryHandlerInterface
{
    public function __construct(private CurrentPublish $publishes) {}

    /** @return list<PageChange> */
    public function handle(QueryInterface $query): array
    {
        if ( ! $query instanceof GetChangesSincePublishQuery) {
            throw new LogicException('Unsupported query: ' . $query::class);
        }

        $since = $this->publishes->lastSuccessful()?->started_at;

        // A change without a revision (a page written outside the command, a restore of a page) shows by `updated_at`.
        $pages = Page::query()
            ->withTrashed()
            ->when(
                $since === null,
                static fn (Builder $pages): Builder => $pages->whereNull('deleted_at'),
                static fn (Builder $pages): Builder => $pages->where(static fn (Builder $changed): Builder => $changed
                    ->where('updated_at', '>', $since)
                    ->orWhere('deleted_at', '>', $since)),
            )
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        /** @var array<int, list<PageRevision>> $revisions newest first */
        $revisions = [];

        foreach (
            PageRevision::query()
                ->whereIn('page_id', $pages->modelKeys())
                ->when($since !== null, static fn (Builder $revisions): Builder => $revisions->where('created_at', '>', $since))
                ->with('user')
                ->orderByDesc('id')
                ->get() as $revision
        ) {
            $revisions[$revision->page_id][] = $revision;
        }

        $changes = [];

        foreach ($pages as $page) {
            $own = $revisions[$page->id] ?? [];

            $changes[] = new PageChange(
                pageId: $page->id,
                title: $page->title,
                slug: $page->slug,
                created: $since === null || $page->created_at?->greaterThan($since) === true,
                deleted: $page->trashed(),
                sources: array_values(array_unique(array_map(static fn (PageRevision $revision): RevisionSource => $revision->source, $own), SORT_REGULAR)),
                revisions: count($own),
                lastAuthor: ($own[0] ?? null)?->user?->name,
                changedAt: $page->trashed() ? $page->deleted_at : $page->updated_at,
            );
        }

        return $changes;
    }
}
