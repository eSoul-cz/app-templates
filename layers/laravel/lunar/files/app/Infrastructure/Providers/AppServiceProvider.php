<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use Filament\Panel;
use Illuminate\Support\ServiceProvider;
use Lunar\Admin\Support\Facades\LunarPanel;

/**
 * Service providers belong in App\Infrastructure\Providers: a provider uses the container and
 * facades, which App\Domain must not use.
 */
final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Lunar's own Filament panel (Filament 4), served at /admin.
        LunarPanel::panel(fn (Panel $panel): Panel => $panel->path('admin'))->register();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void {}
}
