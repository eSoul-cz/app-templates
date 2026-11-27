<?php

declare(strict_types=1);

namespace App\Application\Dto\Cms;

use App\Domain\Cms\RevisionSource;
use Carbon\CarbonInterface;

/** A page that changed since the last successful publish, with what wrote the changes. */
final readonly class PageChange
{
    public function __construct(
        public int $pageId,
        public string $title,
        public string $slug,
        public bool $created,
        public bool $deleted,
        /** @var list<RevisionSource> Distinct sources of the revisions since the publish, newest first; empty for a change without a revision. */
        public array $sources,
        public int $revisions,
        /** Author of the newest revision; null for the system, an assistant that has no user, a deleted user or no revision. */
        public ?string $lastAuthor,
        public ?CarbonInterface $changedAt,
    ) {}
}
