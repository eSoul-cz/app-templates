<?php

declare(strict_types=1);

namespace App\Domain\CQRS\Query\Cms;

use App\Application\Dto\Cms\PendingDraft;
use App\Domain\Cms\RevisionStatus;
use App\Infrastructure\Models\PageRevision;
use Esoul\Cqrs\Attributes\HandlesQuery;
use Esoul\Cqrs\Contracts\QueryHandlerInterface;
use Esoul\Cqrs\Contracts\QueryInterface;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

#[HandlesQuery(GetPendingDraftsQuery::class)]
final readonly class GetPendingDraftsQueryHandler implements QueryHandlerInterface
{
    /** @return list<PendingDraft> */
    public function handle(QueryInterface $query): array
    {
        if ( ! $query instanceof GetPendingDraftsQuery) {
            throw new LogicException('Unsupported query: ' . $query::class);
        }

        return array_values(PageRevision::query()
            ->where('status', RevisionStatus::Pending)
            ->whereHas('page', static fn (Builder $pages): Builder => $pages->withoutTrashed())
            ->with(['page', 'user'])
            ->orderByDesc('id')
            ->get()
            ->map(static fn (PageRevision $draft): PendingDraft => new PendingDraft($draft->id, $draft->page_id, $draft->title, $draft->slug, $draft->user?->name, $draft->created_at))
            ->all());
    }
}
