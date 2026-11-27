<?php

declare(strict_types=1);

namespace App\Domain\CQRS\Query\Cms;

use App\Application\Dto\Cms\RevisionSummary;
use App\Infrastructure\Models\PageRevision;
use Esoul\Cqrs\Attributes\HandlesQuery;
use Esoul\Cqrs\Contracts\QueryHandlerInterface;
use Esoul\Cqrs\Contracts\QueryInterface;
use LogicException;

#[HandlesQuery(GetPageRevisionQuery::class)]
final readonly class GetPageRevisionQueryHandler implements QueryHandlerInterface
{
    /** Shared with the revision list, so a revision has one summary. */
    public static function summary(PageRevision $revision): RevisionSummary
    {
        return new RevisionSummary(
            $revision->id,
            $revision->page_id,
            $revision->source,
            $revision->user?->name,
            $revision->created_at,
            $revision->title,
            $revision->slug,
            $revision->is_published,
            count($revision->blocks ?? []),
            $revision->noindex,
        );
    }

    public function handle(QueryInterface $query): ?RevisionSummary
    {
        if ( ! $query instanceof GetPageRevisionQuery) {
            throw new LogicException('Unsupported query: ' . $query::class);
        }

        $revision = PageRevision::query()->with('user')->find($query->revisionId);

        return $revision === null ? null : self::summary($revision);
    }
}
