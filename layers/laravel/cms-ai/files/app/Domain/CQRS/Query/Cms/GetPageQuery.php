<?php

declare(strict_types=1);

namespace App\Domain\CQRS\Query\Cms;

use App\Application\Dto\Cms\PageDetail;
use Esoul\Cqrs\Contracts\QueryInterface;

/**
 * One page with its blocks; null when there is no such page (a deleted page counts as none).
 *
 * @implements QueryInterface<PageDetail|null>
 */
final readonly class GetPageQuery implements QueryInterface
{
    public function __construct(public int $pageId) {}
}
