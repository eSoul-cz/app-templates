<?php

declare(strict_types=1);

namespace App\Application\Dto\AiConnector;

use DateTimeImmutable;

/** One assistant (an OAuth client) that a user has connected: what the "Connected assistants" page lists. */
final readonly class AssistantConnection
{
    /** @param  list<string>  $scopes */
    public function __construct(
        public string $clientId,
        public string $clientName,
        public int $userId,
        public string $userName,
        public array $scopes,
        public DateTimeImmutable $connectedAt,
        public ?DateTimeImmutable $lastUsedAt,
    ) {}

    /** Key of the row on the page: one connection is one user and one client. */
    public function key(): string
    {
        return $this->userId . ':' . $this->clientId;
    }
}
