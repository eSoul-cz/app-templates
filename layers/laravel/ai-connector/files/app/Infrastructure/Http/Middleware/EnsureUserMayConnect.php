<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Middleware;

use App\Domain\Providers\AiConnectorServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * An assistant never has more rights than the user behind it: whoever may not use the admin may not approve a
 * connection, and a token of a user who lost that right stops working at once. A guest passes (the approval
 * screen sends guests to the login first); routes that need a user put an `auth` middleware before this one.
 */
final class EnsureUserMayConnect
{
    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null) {
            abort_unless(Gate::forUser($user)->allows(AiConnectorServiceProvider::ABILITY), 403);
        }

        return $next($request);
    }
}
