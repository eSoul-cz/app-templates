<?php

declare(strict_types=1);

namespace App\Application\Dto\Cms;

/**
 * The page a save or a restore wrote and the revision that recorded it; `revisionId` is null when nothing changed,
 * so nothing was recorded. `pending` is true when the change was only stored as a draft waiting for approval and the
 * page itself is unchanged (an assistant's change to a published page).
 */
final readonly class SavedPage
{
    public function __construct(public int $pageId, public ?int $revisionId, public bool $pending = false) {}
}
