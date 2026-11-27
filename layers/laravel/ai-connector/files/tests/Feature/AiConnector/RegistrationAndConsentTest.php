<?php

declare(strict_types=1);

namespace Tests\Feature\AiConnector;

use App\Infrastructure\Models\User;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Client;

final class RegistrationAndConsentTest extends AiConnectorTestCase
{
    public function test_an_assistant_registers_itself_as_a_public_client_with_the_connector_scopes(): void
    {
        $this->postJson('/oauth/register', ['client_name' => 'Claude', 'redirect_uris' => [self::REDIRECT_URI]])
            ->assertCreated()
            ->assertJson([
                'redirect_uris' => [self::REDIRECT_URI],
                'token_endpoint_auth_method' => 'none',
                'scope' => 'content:read content:write',
            ]);

        $client = Client::query()->sole();
        $this->assertSame('Claude', $client->name);
        $this->assertFalse($client->confidential());
    }

    public function test_a_redirect_uri_of_an_unknown_site_is_refused(): void
    {
        $this->postJson('/oauth/register', ['client_name' => 'Claude', 'redirect_uris' => ['https://evil.example/callback']])
            ->assertStatus(400)
            ->assertJsonPath('error', 'invalid_redirect_uri');

        $this->assertSame(0, Client::query()->count());
    }

    public function test_registration_is_rate_limited_per_client_address(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/oauth/register', ['client_name' => 'Claude', 'redirect_uris' => [self::REDIRECT_URI]])->assertCreated();
        }

        $this->postJson('/oauth/register', ['client_name' => 'Claude', 'redirect_uris' => [self::REDIRECT_URI]])->assertStatus(429);
    }

    public function test_the_consent_screen_explains_in_english_what_the_assistant_may_do(): void
    {
        app()->setLocale('en');

        $this->consentScreen(['content:read'])
            ->assertOk()
            ->assertSee('Claude wants to connect to')
            ->assertSee('Read content')
            ->assertDontSee('Change content')
            ->assertSee('The assistant will not be able to')
            ->assertSee('sent back to claude.ai')
            ->assertSee('Approve')
            ->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_the_consent_screen_is_in_czech_when_the_application_is(): void
    {
        app()->setLocale('cs');

        $this->consentScreen(['content:read', 'content:write'])
            ->assertOk()
            ->assertSee('se chce připojit k aplikaci')
            ->assertSee('Měnit obsah')
            ->assertSee('Asistent nebude moci')
            ->assertSee('Povolit');
    }

    public function test_a_hostile_client_name_is_escaped_on_the_consent_screen(): void
    {
        $this->consentScreen(['content:read'], name: '<script>alert(1)</script>')
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_cancelling_sends_the_user_back_with_access_denied_and_issues_no_token(): void
    {
        $clientId = $this->registerClient();
        $this->actingAs(User::factory()->create(['is_admin' => true]))->get('/oauth/authorize?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => self::REDIRECT_URI,
            'code_challenge' => str_repeat('a', 43),
            'code_challenge_method' => 'S256',
            'state' => 'xyz',
        ]))->assertOk();

        $location = (string) $this->delete('/oauth/authorize', ['state' => 'xyz', 'client_id' => $clientId, 'auth_token' => session('authToken')])
            ->assertRedirect()
            ->headers->get('Location');

        $this->assertStringContainsString('error=access_denied', $location);
        $this->assertStringNotContainsString('code=', $location);
    }

    /** @param  list<string>  $scopes */
    private function consentScreen(array $scopes, string $name = 'Claude'): TestResponse
    {
        return $this->actingAs(User::factory()->create(['is_admin' => true]))->get('/oauth/authorize?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $this->registerClient($name),
            'redirect_uri' => self::REDIRECT_URI,
            'code_challenge' => str_repeat('a', 43),
            'code_challenge_method' => 'S256',
            'scope' => implode(' ', $scopes),
            'state' => 'xyz',
        ]));
    }
}
