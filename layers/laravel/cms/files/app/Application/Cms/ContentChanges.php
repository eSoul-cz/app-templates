<?php

declare(strict_types=1);

namespace App\Application\Cms;

use App\Models\Page;
use App\Models\SiteSetting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/** Time of the last content change. Content reaches the site only through a publish, so the admin shows pending changes. */
final class ContentChanges
{
    private const string CACHE_KEY = 'cms.content_changed_at';

    public static function touch(): void
    {
        Cache::forever(self::CACHE_KEY, now()->toIso8601String());
    }

    public static function lastChangedAt(): ?CarbonImmutable
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (is_string($cached)) {
            return CarbonImmutable::parse($cached);
        }

        // Empty cache (new deploy, cleared cache): estimate from the latest `updated_at`.
        $latest = collect([Page::query()->max('updated_at'), SiteSetting::query()->max('updated_at')])
            ->filter(static fn (mixed $value): bool => is_string($value))
            ->map(static fn (string $value): CarbonImmutable => CarbonImmutable::parse($value))
            ->max();

        return $latest instanceof CarbonImmutable ? $latest : null;
    }
}
