<?php

declare(strict_types=1);

namespace App\Domain\CQRS\Command\Cms;

use App\Application\Dto\Cms\SavedPage;
use App\Domain\Cms\InvalidPageBlocks;
use App\Domain\Cms\RevisionSource;
use App\Infrastructure\Models\User;
use Esoul\Cqrs\Contracts\CommandInterface;

/**
 * Creates (`pageId` null) or updates a page and records a revision of it. The one way page content changes:
 * the admin passes `source` Admin, an AI connector Ai. A save that changes nothing records no revision (`SavedPage::$revisionId` is null). Only the keys present in `attributes` are written (a key
 * with null clears the value, a missing key leaves it alone); blocks that do not match the block catalogue
 * throw {@see InvalidPageBlocks} and nothing is stored.
 *
 * @implements CommandInterface<SavedPage>
 */
final readonly class SavePageCommand implements CommandInterface
{
    /** @param  array<string, mixed>  $attributes  fillable attributes of the page */
    public function __construct(
        public ?int $pageId,
        public array $attributes,
        public RevisionSource $source,
        public ?User $author,
    ) {}
}
