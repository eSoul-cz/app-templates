<?php

declare(strict_types=1);

namespace App\Application\Dto\Cms;

/** The page a save or a restore wrote and the revision that recorded it; `revisionId` is null when nothing changed, so nothing was recorded. */
final readonly class SavedPage
{
    public function __construct(public int $pageId, public ?int $revisionId) {}
}
