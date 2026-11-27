<?php

declare(strict_types=1);

namespace App\Domain\CQRS\Cms;

use App\Domain\Cms\PageRevisionNotRestorable;
use App\Domain\Cms\RevisionSource;
use App\Domain\Cms\RevisionStatus;
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

    /** The draft of an assistant that waits for approval (at most one per page); call it with the page locked. */
    public function pending(Page $page): ?PageRevision
    {
        return PageRevision::query()->where('page_id', $page->id)->where('status', RevisionStatus::Pending)->first();
    }

    /** @throws PageRevisionNotRestorable */
    public function assertSlugAvailable(PageRevision $revision): void
    {
        if (Page::withTrashed()->where('slug', $revision->slug)->whereKeyNot($revision->page_id)->exists()) {
            throw new PageRevisionNotRestorable("The slug \"{$revision->slug}\" belongs to another page now.");
        }
    }
}
