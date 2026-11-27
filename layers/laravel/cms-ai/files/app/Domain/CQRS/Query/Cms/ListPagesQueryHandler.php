<?php

declare(strict_types=1);

namespace App\Domain\CQRS\Query\Cms;

use App\Application\Dto\Cms\PageSummary;
use App\Infrastructure\Models\Page;
use Esoul\Cqrs\Attributes\HandlesQuery;
use Esoul\Cqrs\Contracts\QueryHandlerInterface;
use Esoul\Cqrs\Contracts\QueryInterface;
use LogicException;

#[HandlesQuery(ListPagesQuery::class)]
final readonly class ListPagesQueryHandler implements QueryHandlerInterface
{
    /** @return list<PageSummary> */
    public function handle(QueryInterface $query): array
    {
        if ( ! $query instanceof ListPagesQuery) {
            throw new LogicException('Unsupported query: ' . $query::class);
        }

        return Page::query()
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->map(static fn (Page $page): PageSummary => new PageSummary($page->id, $page->title, $page->slug, $page->is_published, $page->is_home, $page->updated_at))
            ->values()
            ->all();
    }
}
