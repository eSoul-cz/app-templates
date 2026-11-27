<?php

declare(strict_types=1);

namespace App\Infrastructure\AiConnector;

use App\Application\Contract\AiConnector\ToolCallAuditLog;
use App\Application\Dto\AiConnector\ToolCallRecord;
use App\Infrastructure\Models\AiToolCall;
use Laravel\Passport\Passport;

/** Writes the audit log to the `ai_tool_calls` table; the OAuth client's name is copied, so the row outlives the client. */
final class EloquentToolCallAuditLog implements ToolCallAuditLog
{
    public function record(ToolCallRecord $call): void
    {
        $clientName = $call->clientId === null ? null : Passport::client()->newQuery()->whereKey($call->clientId)->value('name');

        AiToolCall::query()->create([
            'user_id' => $call->userId,
            'client_id' => $call->clientId,
            'client_name' => is_string($clientName) ? $clientName : null,
            'tool' => $call->tool,
            'arguments' => $call->arguments,
            'status' => $call->status,
            'detail' => $call->detail,
            'duration_ms' => $call->durationMs,
            'called_at' => $call->calledAt,
        ]);
    }
}
