<?php

declare(strict_types=1);

namespace App\Domain\CQRS\Command\AiConnector;

use App\Domain\AiConnector\AssistantConnectionNotFound;
use Esoul\Cqrs\Attributes\HandlesCommand;
use Esoul\Cqrs\Contracts\CommandHandlerInterface;
use Esoul\Cqrs\Contracts\CommandInterface;
use Illuminate\Database\ConnectionInterface;
use Laravel\Passport\AuthCode;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;
use LogicException;

#[HandlesCommand(RevokeAssistantConnectionCommand::class)]
final readonly class RevokeAssistantConnectionCommandHandler implements CommandHandlerInterface
{
    public function __construct(private ConnectionInterface $db) {}

    /** @return int the number of access tokens that were revoked */
    public function handle(CommandInterface $command): int
    {
        if ( ! $command instanceof RevokeAssistantConnectionCommand) {
            throw new LogicException('Unsupported command: ' . $command::class);
        }

        // Somebody else's connection is reported as missing: a non-admin learns nothing about it.
        if ($command->actingUser->id !== $command->userId && ! $command->actingUser->is_admin) {
            throw new AssistantConnectionNotFound($command->clientId, $command->userId);
        }

        return $this->db->transaction(function () use ($command): int {
            $tokenIds = Token::query()
                ->where('user_id', $command->userId)
                ->where('client_id', $command->clientId)
                ->where('revoked', false)
                ->lockForUpdate()
                ->pluck('id')
                ->all();

            if ($tokenIds === []) {
                throw new AssistantConnectionNotFound($command->clientId, $command->userId);
            }

            Token::query()->whereIn('id', $tokenIds)->update(['revoked' => true]);
            RefreshToken::query()->whereIn('access_token_id', $tokenIds)->update(['revoked' => true]);
            AuthCode::query()->where('user_id', $command->userId)->where('client_id', $command->clientId)->update(['revoked' => true]);

            return count($tokenIds);
        });
    }
}
