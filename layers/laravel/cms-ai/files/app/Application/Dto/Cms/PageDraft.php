<?php

declare(strict_types=1);

namespace App\Application\Dto\Cms;

use Carbon\CarbonInterface;

/** The pending draft of an assistant for a published page: content that is not live and waits for a person. */
final readonly class PageDraft
{
    /** @param  list<array<string, mixed>>  $blocks */
    public function __construct(
        public int $revisionId,
        public string $title,
        public string $slug,
        public ?string $description,
        public bool $noindex,
        public ?string $ogImage,
        public array $blocks,
        public ?CarbonInterface $proposedAt,
    ) {}
}
