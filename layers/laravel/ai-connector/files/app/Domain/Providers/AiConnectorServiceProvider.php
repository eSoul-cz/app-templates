<?php

declare(strict_types=1);

namespace App\Domain\Providers;

use App\Application\Contract\AiConnector\ToolCallAuditLog;
use App\Domain\AiConnector\ArgumentRedactor;
use App\Domain\AiConnector\ConnectorScope;
use App\Domain\Providers\Filament\AdminPanelProvider;
use App\Infrastructure\AiConnector\EloquentToolCallAuditLog;
use App\Infrastructure\Models\User;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Server\Middleware\AddWwwAuthenticateHeader;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Passport;
use LogicException;

/**
 * Wires the AI connector: Passport as the OAuth server (its own routes are replaced by routes/ai-connector.php so
 * that the global switch covers them), the consent screen, the scopes, the audit log port and the rate limiter.
 */
final class AiConnectorServiceProvider extends ServiceProvider
{
    /** Who may approve a connection and keep using it: whoever may open the admin. */
    public const string ABILITY = 'use-ai-connector';

    public function register(): void
    {
        $this->app->singleton(ToolCallAuditLog::class, EloquentToolCallAuditLog::class);
        $this->app->singleton(ArgumentRedactor::class);

        // Passport reads this guard (the template's config/auth.php stays the skeleton's).
        config()->set('auth.guards.api', config('auth.guards.api') ?? ['driver' => 'passport', 'provider' => 'users']);

        // laravel/mcp's registration endpoint validates redirect URIs against its own config.
        config()->set('mcp.redirect_domains', config('ai-connector.redirect_domains'));

        // The routes (and with them the 404 of a switched-off connector) are ours; this must run before Passport boots.
        Passport::ignoreRoutes();
    }

    public function boot(): void
    {
        Passport::tokensCan([
            ConnectorScope::ContentRead->value => 'Read the content of the application as the signed-in user',
            ConnectorScope::ContentWrite->value => 'Change the content of the application as the signed-in user',
        ]);
        Passport::defaultScopes(ConnectorScope::values());
        Passport::tokensExpireIn(now()->addMinutes(config()->integer('ai-connector.access_token_ttl_minutes')));
        Passport::refreshTokensExpireIn(now()->addDays(config()->integer('ai-connector.refresh_token_ttl_days')));
        Passport::authorizationView('ai-connector.authorize');

        Gate::define(self::ABILITY, static fn (User $user): bool => $user->canAccessPanel(self::panel()));

        // One bucket per access token, so one chatty assistant does not starve the others.
        RateLimiter::for('ai-connector', static function (Request $request): Limit {
            $token = $request->user()?->currentAccessToken();

            return Limit::perMinute(config()->integer('ai-connector.rate_limit_per_minute'))
                ->by($token instanceof AccessToken ? (string) $token->oauth_access_token_id : (string) $request->ip());
        });
        RateLimiter::for('ai-connector-register', static fn (Request $request): Limit => Limit::perMinute(10)->by((string) $request->ip()));

        Route::group([], base_path('routes/ai-connector.php'));

        // A guest who opens the approval screen signs in at the admin login and comes back (Filament honours the intended URL).
        AuthenticationException::redirectUsing(static fn (Request $request): ?string => $request->routeIs('passport.authorizations.*')
            ? self::panel()->getLoginUrl()
            : null);

        // The 401 of /mcp points at our protected-resource document (RFC 9728).
        AddWwwAuthenticateHeader::resolveResourceMetadataUrlUsing(static fn (): string => route('ai-connector.protected-resource'));
    }

    private static function panel(): Panel
    {
        return Filament::getPanel(AdminPanelProvider::PANEL_ID) ?? throw new LogicException('The admin panel is not registered.');
    }
}
