<?php

declare(strict_types=1);

namespace App\Domain\CQRS\Query\Cms;

use App\Application\Dto\Cms\PendingDraft;
use Esoul\Cqrs\Contracts\QueryInterface;

/**
 * Drafts of an assistant that wait for approval, newest first; they are not changes of the site.
 *
 * @implements QueryInterface<list<PendingDraft>>
 */
final readonly class GetPendingDraftsQuery implements QueryInterface {}
