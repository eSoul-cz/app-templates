<?php

declare(strict_types=1);

namespace Tests\Feature\AiConnector;

use App\Infrastructure\Models\User;

final class RateLimitTest extends AiConnectorTestCase
{
    public function test_the_limit_is_per_access_token(): void
    {
        config()->set('ai-connector.rate_limit_per_minute', 2);
        $first = $this->connectAssistant(User::factory()->create(['is_admin' => true]), name: 'Claude');
        $second = $this->connectAssistant(User::factory()->create(['is_admin' => true]), name: 'ChatGPT');

        $this->callTool($first, 'whoami')->assertOk();
        $this->callTool($first, 'whoami')->assertOk();
        $this->callTool($first, 'whoami')->assertStatus(429)->assertHeader('Retry-After');

        // Another token has its own bucket.
        $this->callTool($second, 'whoami')->assertOk();
    }
}
