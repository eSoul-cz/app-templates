<?php

declare(strict_types=1);

namespace App\Domain\Providers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Who may open the Pulse dashboard (`/pulse`, see docs/pulse.md). Pulse only allows the local environment by default; this is the
 * `viewPulse` gate of the application: administrators (a true `is_admin` attribute, which the Filament variants have) and the
 * emails in `config('pulse.viewers')` (PULSE_VIEWERS, comma separated). Everyone else, guests included, gets 403.
 */
final class PulseServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::define('viewPulse', static fn (?Authenticatable $user): bool => self::mayView($user));
    }

    private static function mayView(?Authenticatable $user): bool
    {
        if ( ! $user instanceof Model) {
            return false;
        }

        // The raw attribute: a variant without the `is_admin` column has none, and reading a missing attribute may throw.
        if ((bool) ($user->getAttributes()['is_admin'] ?? false)) {
            return true;
        }

        $email = $user->getAttribute('email');

        return is_string($email) && in_array(mb_strtolower($email), config()->array('pulse.viewers', []), true);
    }
}
