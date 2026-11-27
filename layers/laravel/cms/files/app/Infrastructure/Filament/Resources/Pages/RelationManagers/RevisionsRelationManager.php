<?php

declare(strict_types=1);

namespace App\Infrastructure\Filament\Resources\Pages\RelationManagers;

use App\Domain\Cms\InvalidPageBlocks;
use App\Domain\Cms\PageRevisionNotRestorable;
use App\Domain\Cms\RevisionSource;
use App\Domain\Cms\RevisionStatus;
use App\Domain\CQRS\Command\Cms\ApprovePageRevisionCommand;
use App\Domain\CQRS\Command\Cms\DiscardPageRevisionCommand;
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

/** History of a page: who saved which content, a one-step restore of any revision and the approval of an assistant's pending draft. */
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
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(static fn (RevisionStatus $state): string => match ($state) {
                        RevisionStatus::Applied => 'Applied',
                        RevisionStatus::Pending => 'Waiting for approval',
                        RevisionStatus::Discarded => 'Discarded',
                    })
                    ->color(static fn (RevisionStatus $state): string => match ($state) {
                        RevisionStatus::Applied => 'gray',
                        RevisionStatus::Pending => 'warning',
                        RevisionStatus::Discarded => 'danger',
                    }),
                TextColumn::make('user.name')->label('User')->placeholder('—'),
                TextColumn::make('title'),
                TextColumn::make('slug'),
                TextColumn::make('blocks')
                    ->label('Blocks')
                    ->formatStateUsing(static fn (mixed $state): string => (string) count(is_array($state) ? $state : [])),
            ])
            ->recordClasses(static fn (PageRevision $record): ?string => $record->status === RevisionStatus::Pending ? 'bg-warning-50 dark:bg-warning-400/10' : null)
            ->defaultSort('id', 'desc')
            ->paginated([10, 25])
            ->recordActions([
                Action::make('apply')
                    ->label('Apply')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(static fn (PageRevision $record): bool => $record->status === RevisionStatus::Pending)
                    ->requiresConfirmation()
                    ->modalHeading('Apply this draft?')
                    ->modalDescription('The page gets the content of this draft of the AI assistant, replacing the current content (it stays in the history). The live site changes after the next publish.')
                    ->action(function (PageRevision $record): void {
                        $user = auth()->user();

                        if ( ! $user instanceof User) {
                            return;
                        }

                        try {
                            CommandBus::dispatch(new ApprovePageRevisionCommand($record->id, $user));
                        } catch (InvalidPageBlocks $invalid) {
                            Notification::make()->danger()->title('This draft no longer fits the block catalogue')->body(implode("\n", $invalid->problems))->persistent()->send();

                            return;
                        } catch (PageRevisionNotRestorable $blocked) {
                            Notification::make()->danger()->title('This draft cannot be applied')->body($blocked->getMessage())->persistent()->send();

                            return;
                        }

                        Notification::make()->success()->title('The draft was applied')->send();
                        $this->redirect(PageResource::getUrl('edit', ['record' => $record->page_id]));
                    }),
                Action::make('discard')
                    ->label('Discard')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->visible(static fn (PageRevision $record): bool => $record->status === RevisionStatus::Pending)
                    ->requiresConfirmation()
                    ->modalHeading('Discard this draft?')
                    ->modalDescription('The page stays as it is. The draft remains in the history as discarded.')
                    ->action(function (PageRevision $record): void {
                        try {
                            CommandBus::dispatch(new DiscardPageRevisionCommand($record->id));
                        } catch (PageRevisionNotRestorable $blocked) {
                            Notification::make()->danger()->title('This draft cannot be discarded')->body($blocked->getMessage())->persistent()->send();

                            return;
                        }

                        Notification::make()->success()->title('The draft was discarded')->send();
                    }),
                Action::make('restore')
                    ->visible(static fn (PageRevision $record): bool => $record->status !== RevisionStatus::Pending)
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
