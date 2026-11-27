<?php

declare(strict_types=1);

namespace App\Application\Dto\Cms;

use Carbon\CarbonInterface;

/** A change of a published page proposed by an assistant that waits for a person to apply or discard it. */
final readonly class PendingDraft
{
    public function __construct(
        public int $revisionId,
        public int $pageId,
        public string $title,
        public string $slug,
        public ?string $author,
        public ?CarbonInterface $proposedAt,
    ) {}
}
