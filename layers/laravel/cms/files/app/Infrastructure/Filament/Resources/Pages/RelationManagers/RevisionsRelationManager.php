<?php

declare(strict_types=1);

namespace App\Infrastructure\Filament\Resources\Pages\RelationManagers;

use App\Domain\Cms\InvalidPageBlocks;
use App\Domain\Cms\PageRevisionNotRestorable;
use App\Domain\Cms\RevisionSource;
use App\Domain\CQRS\Command\Cms\RestorePageRevisionCommand;
use App\Infrastructure\Filament\Resources\Pages\PageResource;
use App\Infrastructure\Models\PageRevision;
use App\Infrastructure\Models\User;
use Esoul\LaravelCqrs\Facades\CommandBus;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** History of a page: who saved which content, and a one-step restore of any revision. */
final class RevisionsRelationManager extends RelationManager
{
    protected static string $relationship = 'revisions';

    protected static ?string $title = 'History';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Saved')->dateTime('Y-m-d H:i:s'),
                TextColumn::make('source')
                    ->badge()
                    ->formatStateUsing(static fn (RevisionSource $state): string => $state->label())
                    ->color(static fn (RevisionSource $state): string => $state->color()),
                TextColumn::make('user.name')->label('User')->placeholder('—'),
                TextColumn::make('title'),
                TextColumn::make('slug'),
                TextColumn::make('blocks')
                    ->label('Blocks')
                    ->formatStateUsing(static fn (mixed $state): string => (string) count(is_array($state) ? $state : [])),
            ])
            ->defaultSort('id', 'desc')
            ->paginated([10, 25])
            ->recordActions([
                Action::make('restore')
                    ->label('Restore')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->requiresConfirmation()
                    ->modalHeading('Restore this revision?')
                    ->modalDescription('The page gets the content of this revision. The current content stays in the history.')
                    ->action(function (PageRevision $record): void {
                        $user = auth()->user();

                        try {
                            CommandBus::dispatch(new RestorePageRevisionCommand($record->id, RevisionSource::Admin, $user instanceof User ? $user : null));
                        } catch (InvalidPageBlocks $invalid) {
                            Notification::make()->danger()->title('This revision no longer fits the block catalogue')->body(implode("\n", $invalid->problems))->persistent()->send();

                            return;
                        } catch (PageRevisionNotRestorable $blocked) {
                            Notification::make()->danger()->title('This revision cannot be restored')->body($blocked->getMessage())->persistent()->send();

                            return;
                        }

                        Notification::make()->success()->title('The revision was restored')->send();
                        // Reload the edit form, which still holds the content from before the restore.
                        $this->redirect(PageResource::getUrl('edit', ['record' => $record->page_id]));
                    }),
            ]);
    }
}
