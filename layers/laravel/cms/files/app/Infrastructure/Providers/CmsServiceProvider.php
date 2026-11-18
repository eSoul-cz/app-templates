<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Infrastructure\Cms\Console\PrunePublicMedia;
use App\Infrastructure\Cms\Http\Middleware\AcceptJson;
use App\Infrastructure\Cms\Http\Middleware\RestrictToAllowedIps;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/** Wires the CMS: media disks, /cms-api routes, the prune command and its schedule. */
final class CmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Disks are added here so that config/filesystems.php of the Laravel skeleton stays untouched.
        $disks = config()->array('filesystems.disks');
        $disks['cms-media'] ??= ['driver' => 'local', 'root' => config('cms.media_root'), 'url' => '/cms-media', 'visibility' => 'public', 'throw' => true];
        $disks['cms-public'] ??= ['driver' => 'local', 'root' => config('cms.public_media_root'), 'url' => '/_cms', 'visibility' => 'public', 'throw' => true];
        config()->set('filesystems.disks', $disks);
    }

    public function boot(): void
    {
        // The allowlist goes first: it rejects a foreign address before model binding or the token.
        Route::middleware([RestrictToAllowedIps::class, AcceptJson::class, SubstituteBindings::class])
            ->prefix('cms-api')
            ->group(base_path('routes/cms-api.php'));

        if ($this->app->runningInConsole()) {
            $this->commands([PrunePublicMedia::class]);
        }

        // Site media that no build of the last 7 days referenced (see PrunePublicMedia).
        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule->command(PrunePublicMedia::class)->dailyAt('03:30');
        });
    }
}
