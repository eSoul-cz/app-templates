<?php

declare(strict_types=1);

namespace App\Domain\CQRS\Query\Cms;

use App\Application\Dto\Cms\PageDetail;
use App\Application\Dto\Cms\PageDraft;
use App\Domain\Cms\RevisionStatus;
use App\Infrastructure\Models\Page;
use App\Infrastructure\Models\PageRevision;
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

        $pending = PageRevision::query()->where('page_id', $page->id)->where('status', RevisionStatus::Pending)->first();
        /** @var list<array<string, mixed>> $draftBlocks */
        $draftBlocks = $pending === null ? [] : array_values($pending->blocks ?? []);
        $draft = $pending === null ? null : new PageDraft($pending->id, $pending->title, $pending->slug, $pending->description, $pending->noindex, $pending->og_image, $draftBlocks, $pending->created_at);

        return new PageDetail($page->id, $page->title, $page->slug, $page->description, $page->is_published, $page->is_home, $page->noindex, $page->og_image, $blocks, $page->updated_at, $draft);
    }
}
