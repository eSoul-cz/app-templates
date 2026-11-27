<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers\AiConnector;

use App\Domain\AiConnector\ConnectorScope;
use Illuminate\Http\JsonResponse;

/**
 * The OAuth discovery documents of the MCP authorization spec. laravel/mcp ships the same two documents
 * (Mcp::oauthRoutes()), but it advertises only its own `mcp:use` scope; these name the connector's scopes.
 */
final class DiscoveryController
{
    /** RFC 9728: which resource this is and who issues tokens for it. */
    public function protectedResource(): JsonResponse
    {
        return response()->json([
            'resource' => url(config()->string('ai-connector.path')),
            'authorization_servers' => [url('/')],
            'scopes_supported' => ConnectorScope::values(),
            'bearer_methods_supported' => ['header'],
        ]);
    }

    /** RFC 8414: where to register, authorize and fetch a token. */
    public function authorizationServer(): JsonResponse
    {
        return response()->json([
            'issuer' => url('/'),
            'authorization_endpoint' => route('passport.authorizations.authorize'),
            'token_endpoint' => route('passport.token'),
            'registration_endpoint' => route('ai-connector.register'),
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'scopes_supported' => ConnectorScope::values(),
            'token_endpoint_auth_methods_supported' => ['none'],
        ]);
    }
}
