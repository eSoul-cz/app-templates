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
use App\Domain\Cms\ContentExport;
use App\Domain\Cms\InvalidPageBlocks;
use App\Domain\Cms\RevisionSource;
use App\Domain\CQRS\Command\Cms\SavePageCommand;
use App\Infrastructure\Models\Page;
use Esoul\LaravelCqrs\Facades\CommandBus;
use Illuminate\Validation\Rule;

#[Name('create_page')]
#[Title('Create a page')]
#[Description('Creates a new page as a draft: it is never published and never the home page; a person publishes it in the admin. Saved as a version with the source AI assistant.')]
#[IsDestructive(false)]
final class CreatePageTool extends CmsTool
{
    protected function requiredScope(): ConnectorScope
    {
        return ConnectorScope::ContentWrite;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->description('Page title, at most 255 characters.')->required(),
            'slug' => $schema->string()->description('URL path segment: a-z, 0-9 and single hyphens, at most 100 characters; must be unused.')->required(),
            'description' => $schema->string()->description('Meta description for search engines, at most 300 characters.'),
            'og_image' => $schema->string()->description('Social image: a path returned by upload_image.'),
            'blocks' => $schema->array()->items($schema->object(['type' => $schema->string()->required(), 'data' => $schema->object()->required()]))->description('Content blocks, in order: [{type, data}]. See list_block_types.'),
        ];
    }

    protected function execute(Request $request, User $user): Response|ResponseFactory
    {
        $this->authorizeEdit($user);

        if (($refused = $this->unknownArguments($request, ['title', 'slug', 'description', 'og_image', 'blocks'])) instanceof Response) {
            return $refused;
        }

        $arguments = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:100', 'regex:' . ContentExport::SLUG_PATTERN, Rule::unique(Page::class, 'slug')],
            'description' => ['nullable', 'string', 'max:300'],
            'og_image' => ['nullable', 'string', 'max:255'],
            'blocks' => ['nullable', 'array'],
        ], ['slug.unique' => 'This slug is already used by another page.']);

        $problems = $this->imageProblems($arguments['blocks'] ?? null);

        if (isset($arguments['og_image']) && ! $this->isUploadedImage($arguments['og_image'])) {
            $problems[] = 'og_image is not an uploaded image. Upload it with upload_image.';
        }

        if ($problems !== []) {
            return $this->refused($problems);
        }

        try {
            $saved = CommandBus::dispatch(new SavePageCommand(null, [
                ...$arguments,
                'blocks' => $arguments['blocks'] ?? [],
                'is_published' => false,
                'is_home' => false,
            ], RevisionSource::Ai, $user));
        } catch (InvalidPageBlocks $invalid) {
            return $this->refused($invalid->problems, 'The blocks are not valid, nothing was saved');
        }

        return Response::structured(['page_id' => $saved->pageId, 'revision_id' => $saved->revisionId, 'published' => false, 'note' => 'Saved as a draft. A person publishes it in the admin.']);
    }
}
