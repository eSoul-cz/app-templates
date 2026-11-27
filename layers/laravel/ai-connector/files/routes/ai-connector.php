<?php

declare(strict_types=1);

use App\Infrastructure\Http\Controllers\AiConnector\DiscoveryController;
use App\Infrastructure\Http\Controllers\AiConnector\RegisterClientController;
use App\Infrastructure\Http\Middleware\EnsureAiConnectorEnabled;
use App\Infrastructure\Http\Middleware\EnsureUserMayConnect;
use App\Infrastructure\Mcp\Servers\ConnectorServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use Laravel\Passport\Http\Controllers\ApproveAuthorizationController;
use Laravel\Passport\Http\Controllers\AuthorizationController;
use Laravel\Passport\Http\Controllers\DenyAuthorizationController;

// Every route of the AI connector sits behind the global switch (config ai-connector.enabled): switched off, all of them are 404.
Route::middleware([EnsureAiConnectorEnabled::class])->group(function (): void {
    // Discovery (the MCP authorization spec): where the resource is and who issues tokens for it.
    Route::get('/.well-known/oauth-protected-resource/{path?}', [DiscoveryController::class, 'protectedResource'])
        ->where('path', '.*')
        ->name('ai-connector.protected-resource');
    Route::get('/.well-known/oauth-authorization-server/{path?}', [DiscoveryController::class, 'authorizationServer'])
        ->where('path', '.*')
        ->name('ai-connector.authorization-server');

    // Dynamic client registration: the assistant registers itself, a user still has to approve it.
    Route::post('oauth/register', RegisterClientController::class)
        ->middleware('throttle:ai-connector-register')
        ->name('ai-connector.register');

    // The OAuth server (Passport's own routes are switched off in the provider).
    Route::post('oauth/token', [AccessTokenController::class, 'issueToken'])
        ->middleware('throttle:30,1')
        ->name('passport.token');

    Route::middleware(['web'])->group(function (): void {
        // The consent screen. A guest is sent to the admin login first; a user who may not use the admin is refused.
        Route::get('oauth/authorize', [AuthorizationController::class, 'authorize'])
            ->middleware(EnsureUserMayConnect::class)
            ->name('passport.authorizations.authorize');

        Route::middleware(['auth:web', EnsureUserMayConnect::class])->group(function (): void {
            Route::post('oauth/authorize', [ApproveAuthorizationController::class, 'approve'])->name('passport.authorizations.approve');
            Route::delete('oauth/authorize', [DenyAuthorizationController::class, 'deny'])->name('passport.authorizations.deny');
        });
    });

    // The MCP server: Passport token (guard `api`), a user who may still use the admin, a limit per token.
    Route::middleware(['auth:api', EnsureUserMayConnect::class, 'throttle:ai-connector'])
        ->group(function (): void {
            Mcp::web('/' . config()->string('ai-connector.path'), ConnectorServer::class);
        });
});
