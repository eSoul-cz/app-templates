<?php

declare(strict_types=1);

namespace App\Application\Dto\Cms;

use App\Domain\Cms\RevisionSource;
use Carbon\CarbonInterface;

/** One saved version of a page: who wrote it, through what and when. */
final readonly class RevisionSummary
{
    public function __construct(
        public int $id,
        public int $pageId,
        public RevisionSource $source,
        public ?string $author,
        public ?CarbonInterface $createdAt,
        public string $title,
        public string $slug,
        public bool $isPublished,
        public int $blockCount,
    ) {}
}
