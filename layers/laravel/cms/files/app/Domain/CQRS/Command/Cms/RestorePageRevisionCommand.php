<?php

declare(strict_types=1);

namespace App\Domain\CQRS\Command\Cms;

use App\Application\Dto\Cms\SavedPage;
use App\Domain\Cms\InvalidPageBlocks;
use App\Domain\Cms\PageRevisionNotRestorable;
use App\Domain\Cms\RevisionSource;
use App\Infrastructure\Models\User;
use Esoul\Cqrs\Contracts\CommandInterface;

/**
 * Writes the content of a revision back to its page in one step. This is a save like any other: it records a
 * new revision (the history is never rewritten) and brings a deleted page back. Throws
 * {@see PageRevisionNotRestorable} when another page took the slug since, and {@see InvalidPageBlocks} when the
 * blocks no longer match the block catalogue.
 *
 * @implements CommandInterface<SavedPage>
 */
final readonly class RestorePageRevisionCommand implements CommandInterface
{
    public function __construct(
        public int $revisionId,
        public RevisionSource $source,
        public ?User $author,
    ) {}
}
