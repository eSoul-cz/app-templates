<?php

declare(strict_types=1);

namespace App\Domain\CQRS\Command\Cms;

use App\Application\Dto\Cms\SavedPage;
use App\Domain\Cms\InvalidPageBlocks;
use App\Domain\Cms\PageRevisionNotRestorable;
use App\Infrastructure\Models\User;
use Esoul\Cqrs\Contracts\CommandInterface;

/**
 * A person applies the pending draft of an assistant to its page: the content is saved like an admin save (source
 * Admin, `author` is who approved) and the draft becomes applied. The published state of the page is not taken from
 * the draft. Throws {@see PageRevisionNotRestorable} when the revision is not pending or the slug belongs to another
 * page now, and {@see InvalidPageBlocks} when the blocks no longer match the catalogue.
 *
 * @implements CommandInterface<SavedPage>
 */
final readonly class ApprovePageRevisionCommand implements CommandInterface
{
    public function __construct(
        public int $revisionId,
        public User $author,
    ) {}
}
