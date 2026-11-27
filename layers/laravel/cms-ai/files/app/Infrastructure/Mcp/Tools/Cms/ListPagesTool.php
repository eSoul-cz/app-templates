<?php

declare(strict_types=1);

namespace App\Infrastructure\Mcp\Tools\Cms;

use App\Application\Dto\Cms\PageSummary;
use App\Domain\AiConnector\ConnectorScope;
use App\Domain\CQRS\Query\Cms\ListPagesQuery;
use App\Infrastructure\Models\User;
use Esoul\LaravelCqrs\Facades\QueryBus;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_pages')]
#[Title('List pages')]
#[Description('Lists the pages of the website: id, title, slug, whether the page is published, when it was last changed and whether it has changes that are not on the live site yet.')]
#[IsReadOnly]
final class ListPagesTool extends CmsTool
{
    protected function requiredScope(): ConnectorScope
    {
        return ConnectorScope::ContentRead;
    }

    protected function execute(Request $request, User $user): ResponseFactory
    {
        $this->authorizeView($user);

        $changed = $this->pagesWithUnpublishedChanges();

        return Response::structured([
            'pages' => array_map(
                fn (PageSummary $page): array => $this->summaryData($page, isset($changed[$page->id])),
                QueryBus::execute(new ListPagesQuery()),
            ),
        ]);
    }
}
