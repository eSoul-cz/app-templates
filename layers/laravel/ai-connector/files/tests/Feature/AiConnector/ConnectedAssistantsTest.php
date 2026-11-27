<?php

declare(strict_types=1);

namespace Tests\Feature\AiConnector;

use App\Domain\AiConnector\AssistantConnectionNotFound;
use App\Domain\CQRS\Command\AiConnector\RevokeAssistantConnectionCommand;
use App\Domain\CQRS\Query\AiConnector\ListAssistantConnectionsQuery;
use App\Infrastructure\Filament\Pages\ConnectedAssistants;
use App\Infrastructure\Models\User;
use Esoul\LaravelCqrs\Facades\CommandBus;
use Esoul\LaravelCqrs\Facades\QueryBus;
use Illuminate\Auth\Access\AuthorizationException;
use Laravel\Passport\Client;
use Livewire\Livewire;

final class ConnectedAssistantsTest extends AiConnectorTestCase
{
    public function test_revoking_a_connection_kills_the_access_token_and_the_refresh_token_at_once(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $token = $this->connectAssistant($user);
        $refreshToken = $this->refreshToken;
        $clientId = (string) Client::query()->sole()->getKey();
        $this->callTool($token, 'whoami')->assertOk();

        $this->actingAs($user);
        Livewire::test(ConnectedAssistants::class)
            ->assertCanSeeTableRecords(["{$user->id}:{$clientId}"])
            ->callTableAction('revoke', "{$user->id}:{$clientId}")
            ->assertNotified();

        $this->callTool($token, 'whoami')->assertUnauthorized();
        $this->postJson('/oauth/token', ['grant_type' => 'refresh_token', 'refresh_token' => $refreshToken, 'client_id' => $clientId])
            ->assertStatus(400);
        $this->actingAs($user);
        Livewire::test(ConnectedAssistants::class)->assertCanNotSeeTableRecords(["{$user->id}:{$clientId}"]);
    }

    public function test_a_user_sees_and_revokes_only_their_own_connections(): void
    {
        $jana = User::factory()->create(['is_admin' => true]);
        $petr = User::factory()->create(['is_admin' => true]);
        $janaToken = $this->connectAssistant($jana, name: 'Claude of Jana');
        $petrToken = $this->connectAssistant($petr, name: 'Claude of Petr');
        [$janaClient, $petrClient] = [Client::query()->where('name', 'Claude of Jana')->sole(), Client::query()->where('name', 'Claude of Petr')->sole()];

        $this->actingAs($jana);
        Livewire::test(ConnectedAssistants::class)
            ->assertCanSeeTableRecords(["{$jana->id}:{$janaClient->getKey()}"])
            ->assertCanNotSeeTableRecords(["{$petr->id}:{$petrClient->getKey()}"]);

        $this->expectException(AssistantConnectionNotFound::class);

        try {
            CommandBus::dispatch(new RevokeAssistantConnectionCommand($jana, $petr->id, (string) $petrClient->getKey()));
        } finally {
            // Petr's connection is untouched, Jana's too.
            $this->callTool($petrToken, 'whoami')->assertOk();
            $this->callTool($janaToken, 'whoami')->assertOk();
        }
    }

    public function test_a_non_admin_may_not_list_everybodys_connections(): void
    {
        $this->expectException(AuthorizationException::class);

        QueryBus::execute(new ListAssistantConnectionsQuery(User::factory()->create(['is_admin' => false]))->everybody());
    }

    public function test_an_admin_lists_and_revokes_the_connections_of_everybody(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $other = User::factory()->create(['is_admin' => true]);
        $otherToken = $this->connectAssistant($other, name: 'Claude of the other');
        $client = Client::query()->sole();
        $key = "{$other->id}:{$client->getKey()}";

        $this->actingAs($admin);
        Livewire::test(ConnectedAssistants::class)
            ->assertCanNotSeeTableRecords([$key])
            ->filterTable('everybody', true)
            ->assertCanSeeTableRecords([$key])
            ->callTableAction('revoke', $key);

        $this->callTool($otherToken, 'whoami')->assertUnauthorized();
    }

    public function test_a_connection_is_one_row_however_many_tokens_the_assistant_holds(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $this->connectAssistant($user);
        $this->postJson('/oauth/token', ['grant_type' => 'refresh_token', 'refresh_token' => $this->refreshToken, 'client_id' => (string) Client::query()->sole()->getKey()])->assertOk();

        $connections = QueryBus::execute(new ListAssistantConnectionsQuery($user));

        $this->assertCount(1, $connections);
        $this->assertSame(['content:read', 'content:write'], $connections[0]->scopes);
    }

    public function test_the_page_renders_for_an_admin(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/admin/connected-assistants')
            ->assertOk()
            ->assertSee(url('/mcp'));
    }

    public function test_a_user_without_the_admin_flag_cannot_open_the_page(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get('/admin/connected-assistants')->assertForbidden();
    }
}
