<?php

declare(strict_types=1);

namespace Tests\Feature\AiConnector;

use App\Application\Contract\AiConnector\ToolCallAuditLog;
use App\Domain\AiConnector\ArgumentRedactor;
use App\Domain\AiConnector\ToolCallStatus;
use App\Infrastructure\AiConnector\EloquentToolCallAuditLog;
use App\Infrastructure\Models\AiToolCall;
use App\Infrastructure\Models\User;

final class AuditLogTest extends AiConnectorTestCase
{
    public function test_the_audit_log_is_the_application_contract_with_the_eloquent_adapter(): void
    {
        $this->assertInstanceOf(EloquentToolCallAuditLog::class, $this->app->make(ToolCallAuditLog::class));
    }

    public function test_a_tool_call_is_recorded_with_user_client_tool_and_status(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $token = $this->connectAssistant($user, name: 'Claude for Jana');

        $this->callTool($token, 'whoami')->assertOk();

        $call = AiToolCall::query()->sole();
        $this->assertSame($user->id, $call->user_id);
        $this->assertSame('Claude for Jana', $call->client_name);
        $this->assertNotNull($call->client_id);
        $this->assertSame('whoami', $call->tool);
        $this->assertSame(ToolCallStatus::Succeeded, $call->status);
        $this->assertSame([], $call->arguments);
        $this->assertGreaterThanOrEqual(0, $call->duration_ms);
        $this->assertNotNull($call->called_at);
    }

    public function test_sensitive_arguments_are_redacted_in_the_row(): void
    {
        $token = $this->connectAssistant(User::factory()->create(['is_admin' => true]));

        $this->callTool($token, 'whoami', ['note' => 'hello', 'api_key' => 'sk-live-123', 'nested' => ['password' => 'hunter2']])->assertOk();

        $this->assertSame(
            ['note' => 'hello', 'api_key' => ArgumentRedactor::REDACTED, 'nested' => ['password' => ArgumentRedactor::REDACTED]],
            AiToolCall::query()->sole()->arguments,
        );
        $this->assertStringNotContainsString('sk-live-123', (string) AiToolCall::query()->sole()->getRawOriginal('arguments'));
    }

    public function test_a_refused_call_is_recorded_as_denied(): void
    {
        $token = $this->connectAssistant(User::factory()->create(['is_admin' => true]), ['content:write']);

        $this->callTool($token, 'whoami')->assertOk();

        $call = AiToolCall::query()->sole();
        $this->assertSame(ToolCallStatus::Denied, $call->status);
        $this->assertSame('Missing scope content:read.', $call->detail);
    }

    public function test_a_call_without_a_token_writes_nothing(): void
    {
        $this->mcp(null, 'tools/call', ['name' => 'whoami'])->assertUnauthorized();

        $this->assertSame(0, AiToolCall::query()->count());
    }
}
