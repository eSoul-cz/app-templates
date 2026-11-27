<?php

declare(strict_types=1);

namespace App\Domain\CQRS\Command\Cms;

use App\Application\Dto\Cms\SavedPage;
use App\Domain\Cms\Blocks\BlockValidator;
use App\Domain\Cms\InvalidPageBlocks;
use App\Domain\Cms\RevisionSource;
use App\Domain\Cms\RevisionStatus;
use App\Domain\CQRS\Cms\PageRevisions;
use App\Infrastructure\Models\Page;
use App\Infrastructure\Models\PageRevision;
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

            // The live site changes only when a person acts: a change of a published page by an assistant is kept as a draft.
            if ($page->exists && $page->is_published && $command->source === RevisionSource::Ai) {
                return $this->saveDraft($page, $attributes, $command);
            }

            $page->fill($attributes);

            // A save that changes nothing leaves no revision (an assistant that resends the same text would flood the history).
            if ($page->exists && ! $page->isDirty()) {
                return new SavedPage($page->id, null);
            }

            $page->save();
            // A new model lacks the column defaults (`noindex`, `is_published`) that the snapshot reads.
            $page->refresh();

            return new SavedPage($page->id, $this->revisions->record($page, $command->source, $command->author)->id);
        });
    }

    /**
     * Stores the change as the one pending revision of the page (a later change continues from the draft) and never
     * touches the page. A draft that becomes equal to the live page is discarded.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function saveDraft(Page $page, array $attributes, SavePageCommand $command): SavedPage
    {
        $draft = $this->revisions->pending($page) ?? new PageRevision([...$page->only(PageRevisions::SNAPSHOT), 'page_id' => $page->id]);
        // The published state is not the assistant's to change.
        $draft->fill(array_diff_key(array_intersect_key($attributes, array_flip(PageRevisions::SNAPSHOT)), ['is_published' => true]));

        if ($draft->exists && ! $draft->isDirty()) {
            return new SavedPage($page->id, null);
        }

        $isLive = json_encode($draft->only(PageRevisions::SNAPSHOT)) === json_encode($page->only(PageRevisions::SNAPSHOT));

        if ($isLive) {
            $draft->exists && $draft->update(['status' => RevisionStatus::Discarded]);

            return new SavedPage($page->id, null);
        }

        $draft->fill(['status' => RevisionStatus::Pending, 'source' => $command->source, 'user_id' => $command->author?->id]);
        $draft->created_at = now();
        $draft->save();

        return new SavedPage($page->id, $draft->id, true);
    }
}
