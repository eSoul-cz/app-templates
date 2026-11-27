<?php

declare(strict_types=1);

namespace App\Domain\CQRS\Command\Cms;

use App\Application\Dto\Cms\SavedPage;
use App\Domain\Cms\PageRevisionNotRestorable;
use App\Domain\Cms\RevisionStatus;
use App\Infrastructure\Models\PageRevision;
use Esoul\Cqrs\Attributes\HandlesCommand;
use Esoul\Cqrs\Contracts\CommandHandlerInterface;
use Esoul\Cqrs\Contracts\CommandInterface;
use Illuminate\Database\ConnectionInterface;
use LogicException;

#[HandlesCommand(DiscardPageRevisionCommand::class)]
final readonly class DiscardPageRevisionCommandHandler implements CommandHandlerInterface
{
    public function __construct(private ConnectionInterface $db) {}

    /** @throws PageRevisionNotRestorable */
    public function handle(CommandInterface $command): SavedPage
    {
        if ( ! $command instanceof DiscardPageRevisionCommand) {
            throw new LogicException('Unsupported command: ' . $command::class);
        }

        return $this->db->transaction(function () use ($command): SavedPage {
            $draft = PageRevision::query()->lockForUpdate()->findOrFail($command->revisionId);

            if ($draft->status !== RevisionStatus::Pending) {
                throw new PageRevisionNotRestorable('This draft does not wait for approval any more.');
            }

            $draft->update(['status' => RevisionStatus::Discarded]);

            return new SavedPage($draft->page_id, null);
        });
    }
}
