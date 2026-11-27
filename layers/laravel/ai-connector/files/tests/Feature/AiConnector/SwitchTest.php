<?php

declare(strict_types=1);

namespace Tests\Feature\AiConnector;

use App\Infrastructure\Models\User;

final class SwitchTest extends AiConnectorTestCase
{
    public function test_the_connector_is_off_by_default(): void
    {
        $this->assertStringContainsString("\nAI_CONNECTOR_ENABLED=false\n", (string) file_get_contents(base_path('.env.example')));
    }

    public function test_every_route_answers_404_while_the_switch_is_off(): void
    {
        config()->set('ai-connector.enabled', false);
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->getJson('/.well-known/oauth-protected-resource/mcp')->assertNotFound();
        $this->getJson('/.well-known/oauth-authorization-server')->assertNotFound();
        $this->postJson('/oauth/register', ['client_name' => 'Claude', 'redirect_uris' => [self::REDIRECT_URI]])->assertNotFound();
        $this->get('/oauth/authorize?response_type=code')->assertNotFound();
        $this->postJson('/oauth/token')->assertNotFound();
        $this->post('/oauth/authorize')->assertNotFound();
        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'])->assertNotFound();
        $this->getJson('/mcp')->assertNotFound();
        $this->get('/admin/connected-assistants')->assertNotFound();
    }

    public function test_the_page_is_not_in_the_navigation_while_the_switch_is_off(): void
    {
        config()->set('ai-connector.enabled', false);
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->get('/admin')->assertOk()->assertDontSee('/admin/connected-assistants');
    }

    public function test_the_page_is_in_the_navigation_while_the_switch_is_on(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->get('/admin')->assertOk()->assertSee('/admin/connected-assistants');
    }

    public function test_the_discovery_documents_answer_while_the_switch_is_on(): void
    {
        $this->getJson('/.well-known/oauth-protected-resource/mcp')
            ->assertOk()
            ->assertJson([
                'resource' => url('/mcp'),
                'authorization_servers' => [url('/')],
                'scopes_supported' => ['content:read', 'content:write'],
            ]);
        $this->getJson('/.well-known/oauth-protected-resource')->assertOk()->assertJsonPath('resource', url('/mcp'));

        $this->getJson('/.well-known/oauth-authorization-server')
            ->assertOk()
            ->assertJson([
                'issuer' => url('/'),
                'authorization_endpoint' => url('/oauth/authorize'),
                'token_endpoint' => url('/oauth/token'),
                'registration_endpoint' => url('/oauth/register'),
                'code_challenge_methods_supported' => ['S256'],
                'scopes_supported' => ['content:read', 'content:write'],
            ]);
    }
}
