<?php

declare(strict_types=1);

namespace App\Domain\CQRS\Command\Cms;

use App\Application\Dto\Cms\SavedPage;
use App\Domain\Cms\PageRevisionNotRestorable;
use Esoul\Cqrs\Contracts\CommandInterface;

/**
 * A person turns down the pending draft of an assistant; the page is not touched. Throws
 * {@see PageRevisionNotRestorable} when the revision is not pending.
 *
 * @implements CommandInterface<SavedPage>
 */
final readonly class DiscardPageRevisionCommand implements CommandInterface
{
    public function __construct(public int $revisionId) {}
}
