<?php

declare(strict_types=1);

namespace App\Infrastructure\Models;

use App\Domain\Cms\RevisionSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Snapshot of the content of a page after one save: who wrote it (`user_id`, null for the system or a deleted
 * user) and through what (`source`). Revisions are never updated or deleted.
 *
 * @property int $id
 * @property int $page_id
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property bool $noindex
 * @property string|null $og_image
 * @property array<array-key, mixed>|null $blocks
 * @property bool $is_published
 * @property int|null $user_id
 * @property RevisionSource $source
 * @property Carbon|null $created_at
 * @property-read Page $page
 * @property-read User|null $user
 */
#[Fillable(['page_id', 'title', 'slug', 'description', 'noindex', 'og_image', 'blocks', 'is_published', 'user_id', 'source'])]
class PageRevision extends Model
{
    public const null UPDATED_AT = null;

    /** @return BelongsTo<Page, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'noindex' => 'boolean',
            'blocks' => 'array',
            'is_published' => 'boolean',
            'source' => RevisionSource::class,
        ];
    }
}
