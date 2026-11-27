<?php

declare(strict_types=1);

namespace App\Domain\AiConnector;

use RuntimeException;

/** The connection does not exist, is already revoked, or belongs to somebody the acting user may not manage. */
final class AssistantConnectionNotFound extends RuntimeException
{
    public function __construct(public readonly string $clientId, public readonly int $userId)
    {
        parent::__construct("No active assistant connection of client {$clientId} for user {$userId}.");
    }
}
