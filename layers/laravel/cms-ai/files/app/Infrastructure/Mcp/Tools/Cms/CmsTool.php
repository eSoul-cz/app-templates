<?php

declare(strict_types=1);

namespace App\Infrastructure\Mcp\Tools\Cms;

use App\Application\Contract\Cms\PublicMediaStore;
use App\Application\Dto\Cms\PageDetail;
use App\Application\Dto\Cms\PageSummary;
use App\Domain\Cms\Blocks\BlockCatalogue;
use App\Domain\Cms\Blocks\BlockFieldType;
use App\Domain\CQRS\Query\Cms\GetChangesSincePublishQuery;
use App\Domain\Providers\CmsAiServiceProvider;
use App\Infrastructure\Mcp\Tools\ConnectorTool;
use App\Infrastructure\Models\User;
use Carbon\CarbonInterface;
use Esoul\LaravelCqrs\Facades\QueryBus;
use Illuminate\Support\Facades\Storage;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

/** Shared parts of the CMS tools: the gates, the answers for a refused input and the shape of a page. */
abstract class CmsTool extends ConnectorTool
{
    protected function authorizeView(User $user): void
    {
        $this->authorizeFor($user, CmsAiServiceProvider::VIEW_PAGES);
    }

    protected function authorizeEdit(User $user): void
    {
        $this->authorizeFor($user, CmsAiServiceProvider::EDIT_PAGES);
    }

    /** @param  array<string, mixed>  $arguments */
    protected function int(array $arguments, string $key, int $default = 0): int
    {
        $value = $arguments[$key] ?? $default;

        return is_numeric($value) ? (int) $value : $default;
    }

    /** @param  array<string, mixed>  $arguments */
    protected function string(array $arguments, string $key): string
    {
        $value = $arguments[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    /** @param  list<string>  $allowed */
    protected function unknownArguments(Request $request, array $allowed): ?Response
    {
        $unknown = array_values(array_diff(array_keys($request->all()), $allowed));

        if ($unknown === []) {
            return null;
        }

        return Response::error('Not accepted: ' . implode(', ', $unknown) . '. This tool accepts only: ' . implode(', ', $allowed) . '. An assistant cannot publish, delete or change the home page; a person does that in the admin.');
    }

    /** @param  list<string>  $problems */
    protected function refused(array $problems, string $what = 'The input is not valid, nothing was saved'): Response
    {
        return Response::error($what . ":\n- " . implode("\n- ", $problems));
    }

    /**
     * Image paths of the blocks that are not an upload of this application (the validator checks the shape only).
     *
     * @return list<string>
     */
    protected function imageProblems(mixed $blocks): array
    {
        if ( ! is_array($blocks)) {
            return [];
        }

        $catalogue = app(BlockCatalogue::class);
        $problems = [];

        foreach (array_values($blocks) as $index => $block) {
            if ( ! is_array($block) || ! is_string($block['type'] ?? null) || ! is_array($block['data'] ?? null)) {
                continue;
            }

            $type = $catalogue->find($block['type']);

            if ($type === null) {
                continue;
            }

            foreach ($type->fields as $field) {
                $path = $block['data'][$field->name] ?? null;

                if ($field->type === BlockFieldType::Image && is_string($path) && $path !== '' && ! $this->isUploadedImage($path)) {
                    $problems[] = 'Block ' . ($index + 1) . " ({$type->name}): the image \"{$path}\" is not an uploaded image. Upload it with upload_image and use the path it returns.";
                }
            }
        }

        return $problems;
    }

    protected function isUploadedImage(string $path): bool
    {
        return str_starts_with($path, 'images/') && ! str_contains($path, '..') && Storage::disk(PublicMediaStore::MEDIA_DISK)->exists($path);
    }

    /** @return array<int, true> ids of the pages that changed since the last publish and are not deleted */
    protected function pagesWithUnpublishedChanges(): array
    {
        $ids = [];

        foreach (QueryBus::execute(new GetChangesSincePublishQuery()) as $change) {
            if ( ! $change->deleted) {
                $ids[$change->pageId] = true;
            }
        }

        return $ids;
    }

    /** @return array<string, mixed> */
    protected function summaryData(PageSummary $page, bool $hasUnpublishedChanges): array
    {
        return [
            'id' => $page->id,
            'title' => $page->title,
            'slug' => $page->slug,
            'published' => $page->isPublished,
            'updated_at' => $this->date($page->updatedAt),
            'has_unpublished_changes' => $hasUnpublishedChanges,
        ];
    }

    /** @return array<string, mixed> */
    protected function pageData(PageDetail $page, bool $hasUnpublishedChanges): array
    {
        return [
            'id' => $page->id,
            'title' => $page->title,
            'slug' => $page->slug,
            'description' => $page->description,
            'published' => $page->isPublished,
            'home' => $page->isHome,
            'noindex' => $page->noindex,
            'og_image' => $page->ogImage,
            'blocks' => $page->blocks,
            'updated_at' => $this->date($page->updatedAt),
            'has_unpublished_changes' => $hasUnpublishedChanges,
        ];
    }

    protected function date(?CarbonInterface $date): ?string
    {
        return $date?->toIso8601String();
    }
}
