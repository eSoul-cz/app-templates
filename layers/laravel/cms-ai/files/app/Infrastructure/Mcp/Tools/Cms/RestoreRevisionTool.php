<?php

declare(strict_types=1);

namespace App\Infrastructure\Mcp\Tools\Cms;

use App\Domain\AiConnector\ConnectorScope;
use App\Infrastructure\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use App\Domain\Cms\InvalidPageBlocks;
use App\Domain\Cms\PageRevisionNotRestorable;
use App\Domain\Cms\RevisionSource;
use App\Domain\CQRS\Command\Cms\RestorePageRevisionCommand;
use App\Domain\CQRS\Query\Cms\GetPageQuery;
use App\Domain\CQRS\Query\Cms\GetPageRevisionQuery;
use Esoul\LaravelCqrs\Facades\CommandBus;
use Esoul\LaravelCqrs\Facades\QueryBus;

#[Name('restore_revision')]
#[Title('Restore a page version')]
#[Description('Writes an earlier version of a page back as a new version (the history is kept). Refused when it would change the published state or the slug of a published page, or for a deleted page. Saved with the source AI assistant.')]
#[IsDestructive(false)]
final class RestoreRevisionTool extends CmsTool
{
    protected function requiredScope(): ConnectorScope
    {
        return ConnectorScope::ContentWrite;
    }

    public function schema(JsonSchema $schema): array
    {
        return ['revision_id' => $schema->integer()->description('Id of the version, from list_revisions.')->required()];
    }

    protected function execute(Request $request, User $user): Response|ResponseFactory
    {
        $this->authorizeEdit($user);

        if (($refused = $this->unknownArguments($request, ['revision_id'])) instanceof Response) {
            return $refused;
        }

        $arguments = $request->validate(['revision_id' => ['required', 'integer']]);
        $revision = QueryBus::execute(new GetPageRevisionQuery((int) $arguments['revision_id']));

        if ($revision === null) {
            return Response::error('There is no version with this id.');
        }

        $page = QueryBus::execute(new GetPageQuery($revision->pageId));

        if ($page === null) {
            return Response::error('The page of this version is deleted; an assistant does not bring deleted pages back.');
        }

        $problems = [];

        if ($page->isPublished !== $revision->isPublished) {
            $problems[] = 'This version has a different published state than the page now; an assistant cannot change it.';
        }

        if ($page->isPublished && $page->slug !== $revision->slug) {
            $problems[] = 'This version has another slug and the page is published; the address of a live page must not move.';
        }

        if ($problems !== []) {
            return $this->refused($problems, 'The version was not restored');
        }

        try {
            $saved = CommandBus::dispatch(new RestorePageRevisionCommand($revision->id, RevisionSource::Ai, $user));
        } catch (InvalidPageBlocks $invalid) {
            return $this->refused($invalid->problems, 'The version no longer matches the block types, nothing was saved');
        } catch (PageRevisionNotRestorable $blocked) {
            return Response::error($blocked->getMessage());
        }

        return Response::structured([
            'page_id' => $saved->pageId,
            'revision_id' => $saved->revisionId,
            'changed' => $saved->revisionId !== null,
            'note' => $saved->revisionId === null ? 'The page already had this content, no version was saved.' : 'Restored as a new version. The live site changes only after a person publishes.',
        ]);
    }
}
