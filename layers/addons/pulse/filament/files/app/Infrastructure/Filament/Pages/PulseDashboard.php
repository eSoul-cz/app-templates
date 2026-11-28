<?php

declare(strict_types=1);

namespace App\Infrastructure\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

/**
 * The "Pulse" item of the admin navigation. Pulse has its own dashboard (route `pulse`, `/pulse` by default), so this page is only a
 * link to it, visible to whoever the `viewPulse` gate allows (App\Domain\Providers\PulseServiceProvider); opening its own URL
 * (`/admin/pulse-dashboard`, a path that cannot collide with Pulse's own when `PULSE_PATH` is `admin/pulse`) forwards to the dashboard.
 */
final class PulseDashboard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = 90;

    protected static ?string $navigationLabel = 'Pulse';

    protected static ?string $title = 'Pulse';

    public static function canAccess(): bool
    {
        return Gate::allows('viewPulse');
    }

    public static function getNavigationUrl(): string
    {
        return route('pulse');
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return 'pulse-dashboard';
    }

    public function mount(): void
    {
        $this->redirect(route('pulse'));
    }
}
