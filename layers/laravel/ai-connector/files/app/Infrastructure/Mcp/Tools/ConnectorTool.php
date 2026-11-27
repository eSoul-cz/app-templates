<?php

declare(strict_types=1);

namespace App\Infrastructure\Mcp\Tools;

use App\Application\Contract\AiConnector\ToolCallAuditLog;
use App\Application\Dto\AiConnector\ToolCallRecord;
use App\Domain\AiConnector\ArgumentRedactor;
use App\Domain\AiConnector\ConnectorScope;
use App\Domain\AiConnector\ToolCallStatus;
use App\Infrastructure\Models\User;
use DateTimeImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Passport\AccessToken;
use Throwable;

/**
 * Base class of every tool of the AI connector (the convention, see the README, "AI connector").
 *
 * A tool declares the scope it needs and implements execute(); this class does the rest, in this order: the user
 * is taken from the access token, the scope is checked, execute() runs and the call is written to the audit log.
 * execute() calls an Application command or query with the user (never a model) and asks the same policies as
 * the admin through authorizeFor(), so the assistant never does more than its user may.
 */
abstract class ConnectorTool extends Tool
{
    /** The scope the access token needs for this tool, e.g. ConnectorScope::ContentRead for a tool that only reads. */
    abstract protected function requiredScope(): ConnectorScope;

    abstract protected function execute(Request $request, User $user): Response|ResponseFactory;

    final public function handle(Request $request, ToolCallAuditLog $auditLog, ArgumentRedactor $redactor): Response|ResponseFactory
    {
        $startedAt = new DateTimeImmutable();
        $startedMicro = microtime(true);
        $user = $request->user('api');
        $token = $user instanceof User ? $user->currentAccessToken() : null;
        $status = ToolCallStatus::Succeeded;
        $detail = null;

        try {
            if ( ! $user instanceof User || $token === null) {
                $status = ToolCallStatus::Denied;
                $detail = 'No authenticated user.';

                return Response::error('This connection is not signed in. Connect the assistant again.');
            }

            if ( ! $token->can($this->requiredScope()->value)) {
                $status = ToolCallStatus::Denied;
                $detail = "Missing scope {$this->requiredScope()->value}.";

                return Response::error("This connection was not granted the scope {$this->requiredScope()->value}. Connect the assistant again and approve it.");
            }

            $response = $this->execute($request, $user);

            if ($this->isError($response)) {
                $status = ToolCallStatus::Failed;
            }

            return $response;
        } catch (AuthorizationException $exception) {
            $status = ToolCallStatus::Denied;
            $detail = $exception->getMessage();

            return Response::error('You are not allowed to do this in the application.');
        } catch (Throwable $exception) {
            $status = ToolCallStatus::Failed;
            $detail = $exception::class;

            throw $exception;
        } finally {
            $auditLog->record(new ToolCallRecord(
                userId: $user instanceof User ? $user->id : null,
                clientId: $token instanceof AccessToken ? (string) $token->oauth_client_id : null,
                tool: $this->name(),
                arguments: $redactor->redact($request->all()),
                status: $status,
                durationMs: (int) round((microtime(true) - $startedMicro) * 1000),
                calledAt: $startedAt,
                detail: $detail,
            ));
        }
    }

    /** The same decision the admin makes for this action: a policy or gate; a refusal ends the call as "denied". */
    protected function authorizeFor(User $user, string $ability, mixed $arguments = []): void
    {
        Gate::forUser($user)->authorize($ability, $arguments);
    }

    private function isError(Response|ResponseFactory $response): bool
    {
        $responses = $response instanceof ResponseFactory ? $response->responses() : collect([$response]);

        return $responses->contains(static fn (Response $item): bool => $item->isError());
    }
}
