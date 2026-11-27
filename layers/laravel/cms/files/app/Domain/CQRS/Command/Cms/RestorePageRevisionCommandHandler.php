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

        if (Page::withTrashed()->where('slug', $snapshot['slug'])->whereKeyNot($revision->page_id)->exists()) {
            throw new PageRevisionNotRestorable("The slug \"{$snapshot['slug']}\" belongs to another page now.");
        }

        return $this->db->transaction(function () use ($command, $revision, $snapshot): SavedPage {
            $page = Page::withTrashed()->lockForUpdate()->findOrFail($revision->page_id);

            if ($page->trashed()) {
                $page->restore();
            }

            return $this->commandBus->dispatch(new SavePageCommand($page->id, $snapshot, $command->source, $command->author));
        });
    }
}
