<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** The global switch: while ai-connector.enabled is off, every route of the connector does not exist (404). */
final class EnsureAiConnectorEnabled
{
    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config()->boolean('ai-connector.enabled'), 404);

        return $next($request);
    }
}
