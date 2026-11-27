<?php

declare(strict_types=1);

namespace App\Domain\CQRS\Query\Cms;

use App\Application\Dto\Cms\RevisionSummary;
use App\Infrastructure\Models\Page;
use App\Infrastructure\Models\PageRevision;
use Esoul\Cqrs\Attributes\HandlesQuery;
use Esoul\Cqrs\Contracts\QueryHandlerInterface;
use Esoul\Cqrs\Contracts\QueryInterface;
use LogicException;

#[HandlesQuery(ListPageRevisionsQuery::class)]
final readonly class ListPageRevisionsQueryHandler implements QueryHandlerInterface
{
    /** @return list<RevisionSummary>|null */
    public function handle(QueryInterface $query): ?array
    {
        if ( ! $query instanceof ListPageRevisionsQuery) {
            throw new LogicException('Unsupported query: ' . $query::class);
        }

        if ( ! Page::query()->whereKey($query->pageId)->exists()) {
            return null;
        }

        return array_values(PageRevision::query()
            ->where('page_id', $query->pageId)
            ->with('user')
            ->orderByDesc('id')
            ->limit($query->limit)
            ->get()
            ->map(static fn (PageRevision $revision): RevisionSummary => GetPageRevisionQueryHandler::summary($revision))
            ->all());
    }
}
