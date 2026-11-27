<?php

declare(strict_types=1);

namespace App\Domain\CQRS\Query\AiConnector;

use App\Application\Dto\AiConnector\AssistantConnection;
use App\Infrastructure\Models\AiToolCall;
use App\Infrastructure\Models\User;
use DateTimeImmutable;
use Esoul\Cqrs\Attributes\HandlesQuery;
use Esoul\Cqrs\Contracts\QueryHandlerInterface;
use Esoul\Cqrs\Contracts\QueryInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Passport\Token;
use LogicException;

#[HandlesQuery(ListAssistantConnectionsQuery::class)]
final readonly class ListAssistantConnectionsQueryHandler implements QueryHandlerInterface
{
    /** @return list<AssistantConnection> */
    public function handle(QueryInterface $query): array
    {
        if ( ! $query instanceof ListAssistantConnectionsQuery) {
            throw new LogicException('Unsupported query: ' . $query::class);
        }

        $actor = $query->actingUser;

        if ($query->everybody && ! $actor->is_admin) {
            throw new AuthorizationException('Only an admin lists the connections of other users.');
        }

        $tokens = Token::query()
            ->with('client')
            ->where('revoked', false)
            ->whereNotNull('user_id')
            ->when( ! $query->everybody, static fn (Builder $builder) => $builder->where('user_id', $actor->id))
            // Live while the access token is valid, or while its refresh token can still mint a new one.
            ->where(static fn (Builder $builder) => $builder
                ->where('expires_at', '>', now())
                ->orWhereHas('refreshToken', static fn (Builder $refresh) => $refresh->where('revoked', false)->where('expires_at', '>', now())))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $userIds = $tokens->pluck('user_id')->unique()->all();
        $names = [];

        foreach (User::query()->whereIn('id', $userIds)->get(['id', 'name']) as $named) {
            $names[$named->id] = $named->name;
        }

        $lastUsed = [];

        foreach (AiToolCall::query()->selectRaw('user_id, client_id, max(called_at) as last_used_at')->whereIn('user_id', $userIds)->groupBy('user_id', 'client_id')->get() as $call) {
            $last = $call->getAttribute('last_used_at');

            if (is_string($last)) {
                $lastUsed[$call->user_id . ':' . $call->client_id] = new DateTimeImmutable($last);
            }
        }

        $connections = [];

        foreach ($tokens as $token) {
            $client = $token->client;
            $userId = $token->user_id;

            if ($client === null || $client->revoked || ! is_numeric($userId)) {
                continue;
            }

            $userId = (int) $userId;
            $clientId = $client->id;
            $key = $userId . ':' . $clientId;
            $scopes = array_values(array_filter($token->scopes ?? [], is_string(...)));
            $existing = $connections[$key] ?? null;

            $connections[$key] = new AssistantConnection(
                clientId: $clientId,
                clientName: $client->name,
                userId: $userId,
                userName: $names[$userId] ?? '',
                scopes: array_values(array_unique([...($existing->scopes ?? []), ...$scopes])),
                connectedAt: $existing->connectedAt ?? new DateTimeImmutable($token->created_at?->toDateTimeString() ?? 'now'),
                lastUsedAt: $lastUsed[$key] ?? null,
            );
        }

        $connections = array_values($connections);
        usort($connections, static fn (AssistantConnection $a, AssistantConnection $b): int => [$a->clientName, $a->userId, $a->clientId] <=> [$b->clientName, $b->userId, $b->clientId]);

        return $connections;
    }
}
