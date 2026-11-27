<?php

declare(strict_types=1);

namespace App\Infrastructure\Filament\Pages;

use App\Application\Dto\AiConnector\AssistantConnection;
use App\Domain\AiConnector\AssistantConnectionNotFound;
use App\Domain\CQRS\Command\AiConnector\RevokeAssistantConnectionCommand;
use App\Domain\CQRS\Query\AiConnector\ListAssistantConnectionsQuery;
use App\Infrastructure\Models\User;
use BackedEnum;
use Esoul\LaravelCqrs\Facades\CommandBus;
use Esoul\LaravelCqrs\Facades\QueryBus;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;

/**
 * "Connected assistants": the Claude or ChatGPT connections of the signed-in user, which they can end at once.
 * An admin can also list and end everybody's. Every rule (own versus all, who may revoke) is enforced by the
 * query and the command, not by this page.
 */
final class ConnectedAssistants extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static ?int $navigationSort = 90;

    public static function shouldRegisterNavigation(): bool
    {
        return config()->boolean('ai-connector.enabled');
    }

    public static function getNavigationLabel(): string
    {
        return __('ai-connector.page.navigation');
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return 'connected-assistants';
    }

    public function getTitle(): string
    {
        return __('ai-connector.page.title');
    }

    public function mount(): void
    {
        // The global switch: a switched-off connector has no page.
        abort_unless(config()->boolean('ai-connector.enabled'), 404);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->description(__('ai-connector.page.description'))
            ->records(fn (array $filters): array => $this->rows($filters))
            ->columns([
                TextColumn::make('clientName')->label(__('ai-connector.page.client'))->weight('bold'),
                TextColumn::make('userName')->label(__('ai-connector.page.user'))->visible(fn (): bool => $this->user()->is_admin),
                TextColumn::make('scopes')->label(__('ai-connector.page.scopes'))->badge(),
                TextColumn::make('connectedAt')->label(__('ai-connector.page.connected_at')),
                TextColumn::make('lastUsedAt')->label(__('ai-connector.page.last_used_at'))->placeholder(__('ai-connector.page.never')),
            ])
            ->filters([
                Filter::make('everybody')
                    ->label(__('ai-connector.page.everybody'))
                    ->toggle()
                    ->visible(fn (): bool => $this->user()->is_admin),
            ])
            ->recordActions([
                Action::make('revoke')
                    ->label(__('ai-connector.page.revoke'))
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('ai-connector.page.revoke_heading'))
                    ->modalDescription(__('ai-connector.page.revoke_description'))
                    ->action(function (array $record): void {
                        $userId = $record['userId'] ?? null;
                        $clientId = $record['clientId'] ?? null;

                        if ( ! is_int($userId) || ! is_string($clientId)) {
                            return;
                        }

                        try {
                            CommandBus::dispatch(new RevokeAssistantConnectionCommand($this->user(), $userId, $clientId));
                        } catch (AssistantConnectionNotFound) {
                            Notification::make()->warning()->title(__('ai-connector.page.not_found'))->send();

                            return;
                        }

                        Notification::make()->success()->title(__('ai-connector.page.revoked'))->send();
                    }),
            ])
            ->emptyStateHeading(__('ai-connector.page.empty_heading'))
            ->emptyStateDescription(__('ai-connector.page.empty_description', ['url' => url(config()->string('ai-connector.path'))]))
            ->paginated(false);
    }

    /**
     * @param  array<mixed>  $filters
     * @return array<string, array<string, mixed>>
     */
    private function rows(array $filters): array
    {
        $user = $this->user();
        $everybody = $user->is_admin && data_get($filters, 'everybody.isActive') === true;

        $connections = QueryBus::execute(new ListAssistantConnectionsQuery($user)->everybody($everybody));
        $rows = [];

        foreach ($connections as $connection) {
            /** @var AssistantConnection $connection */
            $rows[$connection->key()] = [
                'clientId' => $connection->clientId,
                'clientName' => $connection->clientName,
                'userId' => $connection->userId,
                'userName' => $connection->userName,
                'scopes' => $connection->scopes,
                'connectedAt' => $connection->connectedAt->format('Y-m-d H:i'),
                'lastUsedAt' => $connection->lastUsedAt?->format('Y-m-d H:i'),
            ];
        }

        return $rows;
    }

    private function user(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
