<?php

declare(strict_types=1);

namespace App\Application\Dto\Cms;

use Carbon\CarbonInterface;

/** A page with its content, as the admin form shows it. */
final readonly class PageDetail
{
    /** @param  list<array<string, mixed>>  $blocks */
    public function __construct(
        public int $id,
        public string $title,
        public string $slug,
        public ?string $description,
        public bool $isPublished,
        public bool $isHome,
        public bool $noindex,
        public ?string $ogImage,
        public array $blocks,
        public ?CarbonInterface $updatedAt,
        public ?PageDraft $draft = null,
    ) {}
}
