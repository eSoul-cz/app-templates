<?php

declare(strict_types=1);

namespace App\Domain\CQRS\Query\Cms;

use App\Application\Dto\Cms\RevisionSummary;
use Esoul\Cqrs\Contracts\QueryInterface;

/**
 * The newest revisions of a page, newest first; null when there is no such page.
 *
 * @implements QueryInterface<list<RevisionSummary>|null>
 */
final readonly class ListPageRevisionsQuery implements QueryInterface
{
    public function __construct(public int $pageId, public int $limit = 20) {}
}
