<?php

declare(strict_types=1);

namespace App\Domain\CQRS\Query\Cms;

use App\Application\Dto\Cms\PageSummary;
use Esoul\Cqrs\Contracts\QueryInterface;

/**
 * The pages that are not deleted, in the order of the admin table (`sort`, then id).
 *
 * @implements QueryInterface<list<PageSummary>>
 */
final readonly class ListPagesQuery implements QueryInterface {}
