<?php

declare(strict_types=1);

namespace App\Domain\CQRS\Command\Cms;

use App\Application\Dto\Cms\SavedPage;
use App\Domain\Cms\InvalidPageBlocks;
use App\Domain\Cms\PageRevisionNotRestorable;
use App\Domain\CQRS\Cms\PageRevisions;
use App\Infrastructure\Models\Page;
use App\Infrastructure\Models\PageRevision;
use Esoul\Cqrs\Attributes\HandlesCommand;
use Esoul\Cqrs\Contracts\CommandBusInterface;
use Esoul\Cqrs\Contracts\CommandHandlerInterface;
use Esoul\Cqrs\Contracts\CommandInterface;
use Illuminate\Database\ConnectionInterface;
use LogicException;

#[HandlesCommand(RestorePageRevisionCommand::class)]
final readonly class RestorePageRevisionCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private ConnectionInterface $db,
        private CommandBusInterface $commandBus,
        private PageRevisions $revisions,
    ) {}

    /**
     * @throws InvalidPageBlocks the save checks the blocks against the catalogue as it is now
     * @throws PageRevisionNotRestorable
     */
    public function handle(CommandInterface $command): SavedPage
    {
        if ( ! $command instanceof RestorePageRevisionCommand) {
            throw new LogicException('Unsupported command: ' . $command::class);
        }

        $revision = PageRevision::query()->findOrFail($command->revisionId);
        $snapshot = $revision->only(PageRevisions::SNAPSHOT);

        $this->revisions->assertSlugAvailable($revision);

        return $this->db->transaction(function () use ($command, $revision, $snapshot): SavedPage {
            $page = Page::withTrashed()->lockForUpdate()->findOrFail($revision->page_id);

            $wasTrashed = $page->trashed();

            if ($wasTrashed) {
                $page->restore();
            }

            $saved = $this->commandBus->dispatch(new SavePageCommand($page->id, $snapshot, $command->source, $command->author));

            // Bringing a deleted page back is a change even when the content is the same as before.
            if ($saved->revisionId === null && $wasTrashed) {
                return new SavedPage($page->id, $this->revisions->record($page->refresh(), $command->source, $command->author)->id);
            }

            return $saved;
        });
    }
}
