<?php

declare(strict_types=1);

namespace App\Domain\CQRS\Query\Cms;

use App\Application\Dto\Cms\PageDetail;
use App\Infrastructure\Models\Page;
use Esoul\Cqrs\Attributes\HandlesQuery;
use Esoul\Cqrs\Contracts\QueryHandlerInterface;
use Esoul\Cqrs\Contracts\QueryInterface;
use LogicException;

#[HandlesQuery(GetPageQuery::class)]
final readonly class GetPageQueryHandler implements QueryHandlerInterface
{
    public function handle(QueryInterface $query): ?PageDetail
    {
        if ( ! $query instanceof GetPageQuery) {
            throw new LogicException('Unsupported query: ' . $query::class);
        }

        $page = Page::query()->find($query->pageId);

        if ($page === null) {
            return null;
        }

        /** @var list<array<string, mixed>> $blocks */
        $blocks = array_values($page->blocks ?? []);

        return new PageDetail($page->id, $page->title, $page->slug, $page->description, $page->is_published, $page->is_home, $page->noindex, $page->og_image, $blocks, $page->updated_at);
    }
}
