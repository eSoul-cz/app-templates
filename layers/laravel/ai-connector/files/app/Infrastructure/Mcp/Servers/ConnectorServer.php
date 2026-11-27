<?php

declare(strict_types=1);

namespace App\Infrastructure\Mcp\Servers;

use App\Infrastructure\Mcp\Tools\WhoamiTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * The MCP server at /mcp. Tools of later layers and of the project are added to $tools; each extends ConnectorTool.
 */
#[Name('Application connector')]
#[Version('1.0.0')]
#[Instructions('Acts as the signed-in user of this application, with the same permissions and nothing more. Call whoami to see who that is and what this connection may do.')]
final class ConnectorServer extends Server
{
    /** @var array<int, class-string<Server\Tool>> */
    protected array $tools = [
        WhoamiTool::class,
    ];
}
