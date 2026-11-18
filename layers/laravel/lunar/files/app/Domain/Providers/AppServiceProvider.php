<?php

declare(strict_types=1);

namespace App\Domain\Providers;

use Filament\Panel;
use Illuminate\Support\ServiceProvider;
use Lunar\Admin\Support\Facades\LunarPanel;

/**
 * Service providers live in App\Domain\Providers, the one namespace of the domain that may use the
 * container and facades (LayerDependencyTest exempts it).
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
