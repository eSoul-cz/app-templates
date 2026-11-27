<?php

declare(strict_types=1);

namespace App\Domain\CQRS\Query\Cms;

use App\Application\Dto\Cms\RevisionSummary;
use Esoul\Cqrs\Contracts\QueryInterface;

/**
 * One revision of a page; null when there is no such revision.
 *
 * @implements QueryInterface<RevisionSummary|null>
 */
final readonly class GetPageRevisionQuery implements QueryInterface
{
    public function __construct(public int $revisionId) {}
}
