<?php

declare(strict_types=1);

namespace App\Application\Dto\Cms;

use Carbon\CarbonInterface;

/** One row of the page list: what a person sees in the admin table. */
final readonly class PageSummary
{
    public function __construct(
        public int $id,
        public string $title,
        public string $slug,
        public bool $isPublished,
        public bool $isHome,
        public ?CarbonInterface $updatedAt,
        public bool $hasDraft = false,
    ) {}
}
