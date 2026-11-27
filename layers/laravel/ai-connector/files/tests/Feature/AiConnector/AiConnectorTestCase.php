<?php

declare(strict_types=1);

namespace Tests\Feature\AiConnector;

use App\Infrastructure\Models\User;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The AI connector, switched on, with a throw-away Passport key pair and a helper that walks the whole OAuth
 * flow (registration, consent, token) the way an assistant does it.
 */
abstract class AiConnectorTestCase extends TestCase
{
    public const string REDIRECT_URI = 'https://claude.ai/api/mcp/auth_callback';

    /** The refresh token of the last connectAssistant() call. */
    protected string $refreshToken = '';

    /** @var array{private: string, public: string}|null */
    private static ?array $keys = null;

    protected function setUp(): void
    {
        parent::setUp();

        self::$keys ??= self::generateKeys();

        config()->set('ai-connector.enabled', true);
        config()->set('passport.private_key', self::$keys['private']);
        config()->set('passport.public_key', self::$keys['public']);
    }

    /**
     * Registers an assistant, lets `$user` approve it and exchanges the code, like Claude or ChatGPT.
     *
     * @param  list<string>|null  $scopes  null requests none, which gives the default scopes (read and write)
     * @return string the access token
     */
    protected function connectAssistant(User $user, ?array $scopes = null, string $name = 'Claude'): string
    {
        $clientId = $this->registerClient($name);
        $verifier = bin2hex(random_bytes(32));
        $query = [
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => self::REDIRECT_URI,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
            'state' => 'xyz',
        ];

        if ($scopes !== null) {
            $query['scope'] = implode(' ', $scopes);
        }

        $this->actingAs($user)->get('/oauth/authorize?' . http_build_query($query))->assertOk();

        $redirect = $this->post('/oauth/authorize', [
            'state' => 'xyz',
            'client_id' => $clientId,
            'auth_token' => session('authToken'),
        ])->assertRedirect()->headers->get('Location');

        parse_str((string) parse_url((string) $redirect, PHP_URL_QUERY), $callback);

        $token = $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'redirect_uri' => self::REDIRECT_URI,
            'code' => $callback['code'],
            'code_verifier' => $verifier,
        ])->assertOk();

        $this->refreshToken = (string) $token->json('refresh_token');

        // The browser session is the user's, not the assistant's: later requests carry the token only.
        $this->app['auth']->forgetGuards();

        return (string) $token->json('access_token');
    }

    protected function registerClient(string $name = 'Claude'): string
    {
        return (string) $this->postJson('/oauth/register', ['client_name' => $name, 'redirect_uris' => [self::REDIRECT_URI]])
            ->assertCreated()
            ->json('client_id');
    }

    /** @param  array<string, mixed>  $arguments */
    protected function callTool(string $token, string $tool, array $arguments = []): TestResponse
    {
        return $this->mcp($token, 'tools/call', ['name' => $tool, 'arguments' => $arguments === [] ? (object) [] : $arguments]);
    }

    /** @param  array<string, mixed>  $params */
    protected function mcp(?string $token, string $method, array $params = []): TestResponse
    {
        // A real request starts with no resolved user; the guards of this test process would keep the previous one.
        $this->app['auth']->forgetGuards();

        $headers = ['Accept' => 'application/json, text/event-stream'];

        if ($token !== null) {
            $headers['Authorization'] = "Bearer {$token}";
        }

        return $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params], $headers);
    }

    /** @return array{private: string, public: string} */
    private static function generateKeys(): array
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $private);

        return ['private' => $private, 'public' => openssl_pkey_get_details($key)['key']];
    }
}
