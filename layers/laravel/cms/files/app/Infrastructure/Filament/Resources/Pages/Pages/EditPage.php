<?php

declare(strict_types=1);

namespace App\Infrastructure\Filament\Resources\Pages\Pages;

use App\Domain\Cms\InvalidPageBlocks;
use App\Domain\Cms\RevisionSource;
use App\Domain\CQRS\Command\Cms\SavePageCommand;
use App\Infrastructure\Filament\Resources\Pages\PageResource;
use App\Infrastructure\Models\Page;
use App\Infrastructure\Models\User;
use Esoul\LaravelCqrs\Facades\CommandBus;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use LogicException;

final class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    /** @param  array<string, mixed>  $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if ( ! $record instanceof Page) {
            throw new LogicException('The page form edits a page.');
        }

        $user = auth()->user();

        try {
            CommandBus::dispatch(new SavePageCommand($record->id, $data, RevisionSource::Admin, $user instanceof User ? $user : null));
        } catch (InvalidPageBlocks $invalid) {
            Notification::make()->danger()->title('The blocks are not valid')->body(implode("\n", $invalid->problems))->persistent()->send();

            // Stops the save without an error page, like halt() does.
            throw new Halt();
        }

        return $record->refresh();
    }
}
