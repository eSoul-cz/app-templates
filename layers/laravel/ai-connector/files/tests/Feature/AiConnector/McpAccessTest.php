<?php

declare(strict_types=1);

namespace Tests\Feature\AiConnector;

use App\Infrastructure\Models\User;

final class McpAccessTest extends AiConnectorTestCase
{
    public function test_an_unauthenticated_call_is_401_and_points_at_the_protected_resource_document(): void
    {
        $response = $this->mcp(null, 'tools/list')->assertUnauthorized();

        $this->assertSame(
            'Bearer realm="mcp", resource_metadata="' . url('/.well-known/oauth-protected-resource') . '"',
            $response->headers->get('WWW-Authenticate'),
        );
    }

    public function test_a_garbage_token_is_401(): void
    {
        $this->mcp('not-a-token', 'tools/list')->assertUnauthorized()->assertHeader('WWW-Authenticate');
    }

    public function test_whoami_returns_the_user_and_the_granted_scopes(): void
    {
        $token = $this->connectAssistant(User::factory()->create(['name' => 'Jana Nováková', 'is_admin' => true]), ['content:read']);

        $response = $this->callTool($token, 'whoami')->assertOk();

        $this->assertNull($response->json('error'));
        $this->assertSame(['name' => 'Jana Nováková', 'scopes' => ['content:read']], $response->json('result.structuredContent'));
    }

    public function test_an_assistant_that_asks_for_no_scope_gets_read_and_write(): void
    {
        $token = $this->connectAssistant(User::factory()->create(['is_admin' => true]));

        $this->assertSame(['content:read', 'content:write'], $this->callTool($token, 'whoami')->json('result.structuredContent.scopes'));
    }

    public function test_a_token_without_the_scope_the_tool_needs_is_refused(): void
    {
        $token = $this->connectAssistant(User::factory()->create(['is_admin' => true]), ['content:write']);

        $response = $this->callTool($token, 'whoami')->assertOk();

        $this->assertTrue($response->json('result.isError'));
        $this->assertStringContainsString('content:read', (string) $response->json('result.content.0.text'));
        $this->assertNull($response->json('result.structuredContent'));
    }

    public function test_the_tool_list_names_whoami_and_the_tools_other_layers_registered(): void
    {
        $token = $this->connectAssistant(User::factory()->create(['is_admin' => true]));
        $registered = array_map(static fn (string $tool): string => new $tool()->name(), config()->array('ai-connector.tools'));

        $this->assertEqualsCanonicalizing(['whoami', ...$registered], array_column($this->mcp($token, 'tools/list')->assertOk()->json('result.tools'), 'name'));
    }

    public function test_a_user_who_may_no_longer_use_the_admin_loses_the_connection(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $token = $this->connectAssistant($user);
        $this->callTool($token, 'whoami')->assertOk();

        $user->forceFill(['is_admin' => false])->save();

        $this->callTool($token, 'whoami')->assertForbidden();
    }

    public function test_a_user_who_may_not_use_the_admin_cannot_approve_a_connection(): void
    {
        $clientId = $this->registerClient();

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get('/oauth/authorize?' . http_build_query([
                'response_type' => 'code',
                'client_id' => $clientId,
                'redirect_uri' => self::REDIRECT_URI,
                'code_challenge' => str_repeat('a', 43),
                'code_challenge_method' => 'S256',
            ]))
            ->assertForbidden();
        $this->post('/oauth/authorize', ['client_id' => $clientId])->assertForbidden();
    }

    public function test_a_guest_who_opens_the_consent_screen_is_sent_to_the_admin_login(): void
    {
        $clientId = $this->registerClient();

        $this->get('/oauth/authorize?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => self::REDIRECT_URI,
            'code_challenge' => str_repeat('a', 43),
            'code_challenge_method' => 'S256',
        ]))->assertRedirect('/admin/login');
    }

    public function test_the_method_check_of_the_endpoint_is_kept(): void
    {
        $token = $this->connectAssistant(User::factory()->create(['is_admin' => true]));

        $this->getJson('/mcp', ['Authorization' => "Bearer {$token}"])->assertStatus(405)->assertHeader('Allow', 'POST');
    }
}
