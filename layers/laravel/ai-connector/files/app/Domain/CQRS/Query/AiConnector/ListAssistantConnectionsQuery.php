<?php

declare(strict_types=1);

namespace App\Domain\CQRS\Query\AiConnector;

use App\Application\Dto\AiConnector\AssistantConnection;
use App\Infrastructure\Models\User;
use Esoul\Cqrs\Contracts\QueryInterface;

/**
 * The assistants connected to the application: the acting user's own, or, for an admin who asks for it,
 * everybody's. One entry per user and assistant, however many tokens the assistant holds.
 *
 * @implements QueryInterface<list<AssistantConnection>>
 */
final class ListAssistantConnectionsQuery implements QueryInterface
{
    public private(set) bool $everybody = false;

    public function __construct(public readonly User $actingUser) {}

    public function everybody(bool $value = true): self
    {
        $this->everybody = $value;

        return $this;
    }
}
