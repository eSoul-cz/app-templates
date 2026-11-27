<?php

declare(strict_types=1);

namespace App\Infrastructure\Mcp\Tools\Cms;

use App\Domain\AiConnector\ConnectorScope;
use App\Domain\CQRS\Query\Cms\GetPageQuery;
use App\Infrastructure\Models\User;
use Esoul\LaravelCqrs\Facades\QueryBus;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_page')]
#[Title('Get a page')]
#[Description('Returns one page with all its fields and its content blocks (use list_block_types to see what a block may hold).')]
#[IsReadOnly]
final class GetPageTool extends CmsTool
{
    public function schema(JsonSchema $schema): array
    {
        return ['page_id' => $schema->integer()->description('Id of the page, from list_pages.')->required()];
    }

    protected function requiredScope(): ConnectorScope
    {
        return ConnectorScope::ContentRead;
    }

    protected function execute(Request $request, User $user): Response|ResponseFactory
    {
        $this->authorizeView($user);

        $arguments = $request->validate(['page_id' => ['required', 'integer']]);
        $page = QueryBus::execute(new GetPageQuery($this->int($arguments, 'page_id')));

        if ($page === null) {
            return Response::error('There is no page with this id.');
        }

        return Response::structured($this->pageData($page, isset($this->pagesWithUnpublishedChanges()[$page->id])));
    }
}
