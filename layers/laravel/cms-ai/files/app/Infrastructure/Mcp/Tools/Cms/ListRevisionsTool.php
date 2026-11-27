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
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use App\Application\Dto\Cms\RevisionSummary;
use App\Domain\CQRS\Query\Cms\ListPageRevisionsQuery;
use Esoul\LaravelCqrs\Facades\QueryBus;

#[Name('list_revisions')]
#[Title('List page versions')]
#[Description('Lists the saved versions of a page, newest first: id, who wrote it (person or AI assistant), when, title and number of blocks. Use an id with restore_revision.')]
#[IsReadOnly]
final class ListRevisionsTool extends CmsTool
{
    protected function requiredScope(): ConnectorScope
    {
        return ConnectorScope::ContentRead;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'page_id' => $schema->integer()->description('Id of the page, from list_pages.')->required(),
            'limit' => $schema->integer()->description('How many versions, 1 to 50; default 20.'),
        ];
    }

    protected function execute(Request $request, User $user): Response|ResponseFactory
    {
        $this->authorizeView($user);

        $arguments = $request->validate(['page_id' => ['required', 'integer'], 'limit' => ['sometimes', 'integer', 'min:1', 'max:50']]);
        $revisions = QueryBus::execute(new ListPageRevisionsQuery((int) $arguments['page_id'], (int) ($arguments['limit'] ?? 20)));

        if ($revisions === null) {
            return Response::error('There is no page with this id.');
        }

        return Response::structured(['revisions' => array_map(fn (RevisionSummary $revision): array => [
            'id' => $revision->id,
            'source' => $revision->source->value,
            'author' => $revision->author,
            'created_at' => $this->date($revision->createdAt),
            'title' => $revision->title,
            'slug' => $revision->slug,
            'published' => $revision->isPublished,
            'block_count' => $revision->blockCount,
        ], $revisions)]);
    }
}
