<?php

declare(strict_types=1);

namespace App\Infrastructure\Mcp\Tools;

use App\Domain\AiConnector\ConnectorScope;
use App\Infrastructure\Models\User;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('whoami')]
#[Title('Who am I')]
#[Description('Returns the name of the signed-in user this assistant acts as and the scopes this connection was granted.')]
#[IsReadOnly]
final class WhoamiTool extends ConnectorTool
{
    protected function requiredScope(): ConnectorScope
    {
        return ConnectorScope::ContentRead;
    }

    protected function execute(Request $request, User $user): ResponseFactory
    {
        $scopes = array_values(array_filter(ConnectorScope::values(), static fn (string $scope): bool => $user->currentAccessToken()?->can($scope) === true));

        return Response::structured([
            'name' => $user->name,
            'scopes' => $scopes,
        ]);
    }
}
