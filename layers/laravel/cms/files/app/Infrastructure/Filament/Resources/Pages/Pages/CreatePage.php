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
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

final class CreatePage extends CreateRecord
{
    protected static string $resource = PageResource::class;

    /** @param  array<string, mixed>  $data */
    protected function handleRecordCreation(array $data): Model
    {
        $user = auth()->user();

        try {
            $saved = CommandBus::dispatch(new SavePageCommand(null, $data, RevisionSource::Admin, $user instanceof User ? $user : null));
        } catch (InvalidPageBlocks $invalid) {
            Notification::make()->danger()->title('The blocks are not valid')->body(implode("\n", $invalid->problems))->persistent()->send();

            // Stops the save without an error page, like halt() does.
            throw new Halt();
        }

        return Page::query()->findOrFail($saved->pageId);
    }
}
