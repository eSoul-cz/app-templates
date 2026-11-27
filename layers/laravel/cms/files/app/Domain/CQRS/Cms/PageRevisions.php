<?php

declare(strict_types=1);

namespace App\Domain\CQRS\Cms;

use App\Domain\Cms\RevisionSource;
use App\Infrastructure\Models\Page;
use App\Infrastructure\Models\PageRevision;
use App\Infrastructure\Models\User;

/** Writes the revision of a page; shared by the commands that change a page, so a snapshot is defined in one place. */
final readonly class PageRevisions
{
    /** The attributes a revision keeps and a restore writes back (not `is_home` and `sort`: the place of a page is not its content). */
    public const array SNAPSHOT = ['title', 'slug', 'description', 'noindex', 'og_image', 'blocks', 'is_published'];

    public function record(Page $page, RevisionSource $source, ?User $author): PageRevision
    {
        return PageRevision::query()->create([
            'page_id' => $page->id,
            'user_id' => $author?->id,
            'source' => $source,
            ...$page->only(self::SNAPSHOT),
        ]);
    }
}
