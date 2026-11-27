<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers\AiConnector;

use App\Domain\AiConnector\ConnectorScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Mcp\Server\Http\Controllers\OAuthRegisterController;

/**
 * Dynamic client registration (RFC 7591) as laravel/mcp implements it: a public client, authorization code
 * with PKCE, redirect URIs checked against ai-connector.redirect_domains. Only the answer differs: it names the
 * connector's scopes instead of laravel/mcp's `mcp:use`.
 */
final class RegisterClientController extends OAuthRegisterController
{
    public function __invoke(Request $request): JsonResponse
    {
        $response = parent::__invoke($request);

        if ($response->getStatusCode() === 201) {
            $data = $response->getData(true);
            $response->setData([...(is_array($data) ? $data : []), 'scope' => implode(' ', ConnectorScope::values())]);
        }

        return $response;
    }
}
