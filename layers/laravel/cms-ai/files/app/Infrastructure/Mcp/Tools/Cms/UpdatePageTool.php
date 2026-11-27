<?php

declare(strict_types=1);

namespace App\Infrastructure\Mcp\Tools\Cms;

use App\Domain\AiConnector\ConnectorScope;
use App\Domain\Cms\ContentExport;
use App\Domain\Cms\InvalidPageBlocks;
use App\Domain\Cms\RevisionSource;
use App\Domain\CQRS\Command\Cms\SavePageCommand;
use App\Domain\CQRS\Query\Cms\GetPageQuery;
use App\Infrastructure\Models\Page;
use App\Infrastructure\Models\User;
use Esoul\LaravelCqrs\Facades\CommandBus;
use Esoul\LaravelCqrs\Facades\QueryBus;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('update_page')]
#[Title('Update a page')]
#[Description('Changes the title, description, social image, slug (only of a page that is not published) or blocks of a page; send only what changes (blocks replace all blocks). It cannot publish, unpublish or move the home page. Saved as a version with the source AI assistant; nothing is saved when nothing changes.')]
#[IsDestructive(false)]
#[IsIdempotent]
final class UpdatePageTool extends CmsTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'page_id' => $schema->integer()->description('Id of the page, from list_pages.')->required(),
            'title' => $schema->string()->description('New title, at most 255 characters.'),
            'slug' => $schema->string()->description('New slug; refused for a published page.'),
            'description' => $schema->string()->description('New meta description, at most 300 characters; null clears it.')->nullable(),
            'og_image' => $schema->string()->description('New social image: a path returned by upload_image; null clears it.')->nullable(),
            'blocks' => $schema->array()->items($schema->object(['type' => $schema->string()->required(), 'data' => $schema->object()->required()]))->description('The complete new list of blocks, in order: [{type, data}]. See list_block_types.'),
        ];
    }

    protected function requiredScope(): ConnectorScope
    {
        return ConnectorScope::ContentWrite;
    }

    protected function execute(Request $request, User $user): Response|ResponseFactory
    {
        $this->authorizeEdit($user);

        if (($refused = $this->unknownArguments($request, ['page_id', 'title', 'slug', 'description', 'og_image', 'blocks'])) instanceof Response) {
            return $refused;
        }

        $sent = $request->all();
        $page = QueryBus::execute(new GetPageQuery($this->int($request->validate(['page_id' => ['required', 'integer']]), 'page_id')));

        if ($page === null) {
            return Response::error('There is no page with this id.');
        }

        $arguments = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:100', 'regex:' . ContentExport::SLUG_PATTERN, Rule::unique(Page::class, 'slug')->ignore($page->id)],
            'description' => ['sometimes', 'nullable', 'string', 'max:300'],
            'og_image' => ['sometimes', 'nullable', 'string', 'max:255'],
            'blocks' => ['sometimes', 'array'],
        ], ['slug.unique' => 'This slug is already used by another page.']);

        $problems = $this->imageProblems($arguments['blocks'] ?? null);

        if (is_string($arguments['og_image'] ?? null) && ! $this->isUploadedImage($arguments['og_image'])) {
            $problems[] = 'og_image is not an uploaded image. Upload it with upload_image.';
        }

        if ($page->isPublished && array_key_exists('slug', $sent) && $sent['slug'] !== $page->slug) {
            $problems[] = 'The slug of a published page cannot be changed by an assistant: the address of a live page must not move.';
        }

        if ($problems !== []) {
            return $this->refused($problems);
        }

        try {
            $saved = CommandBus::dispatch(new SavePageCommand($page->id, $arguments, RevisionSource::Ai, $user));
        } catch (InvalidPageBlocks $invalid) {
            return $this->refused($invalid->problems, 'The blocks are not valid, nothing was saved');
        }

        return Response::structured([
            'page_id' => $saved->pageId,
            'revision_id' => $saved->revisionId,
            'changed' => $saved->revisionId !== null,
            'note' => $saved->revisionId === null ? 'Nothing changed, no version was saved.' : 'Saved as a new version. The live site changes only after a person publishes.',
        ]);
    }
}
