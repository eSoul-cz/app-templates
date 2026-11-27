<?php

declare(strict_types=1);

namespace App\Domain\CQRS\Command\Cms;

use App\Application\Dto\Cms\SavedPage;
use App\Domain\Cms\Blocks\BlockValidator;
use App\Domain\Cms\InvalidPageBlocks;
use App\Domain\CQRS\Cms\PageRevisions;
use App\Infrastructure\Models\Page;
use Esoul\Cqrs\Attributes\HandlesCommand;
use Esoul\Cqrs\Contracts\CommandHandlerInterface;
use Esoul\Cqrs\Contracts\CommandInterface;
use Illuminate\Database\ConnectionInterface;
use LogicException;

#[HandlesCommand(SavePageCommand::class)]
final readonly class SavePageCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private ConnectionInterface $db,
        private BlockValidator $blocks,
        private PageRevisions $revisions,
    ) {}

    /** @throws InvalidPageBlocks */
    public function handle(CommandInterface $command): SavedPage
    {
        if ( ! $command instanceof SavePageCommand) {
            throw new LogicException('Unsupported command: ' . $command::class);
        }

        $attributes = array_intersect_key($command->attributes, array_flip(new Page()->getFillable()));

        if (array_key_exists('blocks', $attributes)) {
            $problems = $this->blocks->validate($attributes['blocks']);

            if ($problems !== []) {
                throw new InvalidPageBlocks($problems);
            }

            // The Builder state is keyed by item uuids; a stored list is the plain shape.
            $attributes['blocks'] = is_array($attributes['blocks']) ? array_values($attributes['blocks']) : null;
        }

        return $this->db->transaction(function () use ($command, $attributes): SavedPage {
            $page = $command->pageId === null ? new Page() : Page::query()->lockForUpdate()->findOrFail($command->pageId);
            $page->fill($attributes)->save();
            // A new model lacks the column defaults (`noindex`, `is_published`) that the snapshot reads.
            $page->refresh();

            return new SavedPage($page->id, $this->revisions->record($page, $command->source, $command->author)->id);
        });
    }
}
