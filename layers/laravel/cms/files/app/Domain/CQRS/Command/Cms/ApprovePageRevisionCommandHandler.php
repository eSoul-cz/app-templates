<?php

declare(strict_types=1);

namespace App\Domain\CQRS\Command\Cms;

use App\Application\Dto\Cms\SavedPage;
use App\Domain\Cms\InvalidPageBlocks;
use App\Domain\Cms\PageRevisionNotRestorable;
use App\Domain\Cms\RevisionSource;
use App\Domain\Cms\RevisionStatus;
use App\Domain\CQRS\Cms\PageRevisions;
use App\Infrastructure\Models\PageRevision;
use Esoul\Cqrs\Attributes\HandlesCommand;
use Esoul\Cqrs\Contracts\CommandBusInterface;
use Esoul\Cqrs\Contracts\CommandHandlerInterface;
use Esoul\Cqrs\Contracts\CommandInterface;
use Illuminate\Database\ConnectionInterface;
use LogicException;

#[HandlesCommand(ApprovePageRevisionCommand::class)]
final readonly class ApprovePageRevisionCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private ConnectionInterface $db,
        private CommandBusInterface $commandBus,
        private PageRevisions $revisions,
    ) {}

    /**
     * @throws InvalidPageBlocks
     * @throws PageRevisionNotRestorable
     */
    public function handle(CommandInterface $command): SavedPage
    {
        if ( ! $command instanceof ApprovePageRevisionCommand) {
            throw new LogicException('Unsupported command: ' . $command::class);
        }

        return $this->db->transaction(function () use ($command): SavedPage {
            $draft = PageRevision::query()->lockForUpdate()->findOrFail($command->revisionId);

            if ($draft->status !== RevisionStatus::Pending) {
                throw new PageRevisionNotRestorable('This draft does not wait for approval any more.');
            }

            $this->revisions->assertSlugAvailable($draft);

            $saved = $this->commandBus->dispatch(new SavePageCommand(
                $draft->page_id,
                array_diff_key($draft->only(PageRevisions::SNAPSHOT), ['is_published' => true]),
                RevisionSource::Admin,
                $command->author,
            ));

            $draft->update(['status' => RevisionStatus::Applied]);

            return $saved;
        });
    }
}
