<?php

declare(strict_types=1);

namespace App\Domain\CQRS\Command\AiConnector;

use App\Infrastructure\Models\User;
use Esoul\Cqrs\Contracts\CommandInterface;

/**
 * Ends the connection of one user and one assistant at once: every access token, refresh token and unused
 * authorization code of the pair is revoked. The acting user revokes their own; an admin revokes anybody's.
 *
 * @implements CommandInterface<int>
 */
final readonly class RevokeAssistantConnectionCommand implements CommandInterface
{
    public function __construct(
        public User $actingUser,
        public int $userId,
        public string $clientId,
    ) {}
}
