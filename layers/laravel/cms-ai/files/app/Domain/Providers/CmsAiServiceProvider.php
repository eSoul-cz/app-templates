<?php

declare(strict_types=1);

namespace App\Domain\Providers;

use App\Infrastructure\Mcp\Tools\Cms\CreatePageTool;
use App\Infrastructure\Mcp\Tools\Cms\GetPageTool;
use App\Infrastructure\Mcp\Tools\Cms\ListBlockTypesTool;
use App\Infrastructure\Mcp\Tools\Cms\ListPagesTool;
use App\Infrastructure\Mcp\Tools\Cms\ListRevisionsTool;
use App\Infrastructure\Mcp\Tools\Cms\RestoreRevisionTool;
use App\Infrastructure\Mcp\Tools\Cms\UpdatePageTool;
use App\Infrastructure\Mcp\Tools\Cms\UploadImageTool;
use App\Infrastructure\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Adds the CMS tools to the AI connector (ConnectorServer reads the config key `ai-connector.tools`) and defines
 * the two gates they ask. In the admin whoever opens the panel edits pages, so both gates follow the connector's
 * own gate; a project with roles redefines them with Gate::define() to follow its rule, and the tools obey.
 */
final class CmsAiServiceProvider extends ServiceProvider
{
    public const string VIEW_PAGES = 'view-cms-pages';

    public const string EDIT_PAGES = 'edit-cms-pages';

    public function register(): void
    {
        config()->set('ai-connector.tools', [
            ...config()->array('ai-connector.tools', []),
            ListPagesTool::class,
            GetPageTool::class,
            ListBlockTypesTool::class,
            CreatePageTool::class,
            UpdatePageTool::class,
            UploadImageTool::class,
            ListRevisionsTool::class,
            RestoreRevisionTool::class,
        ]);
    }

    public function boot(): void
    {
        foreach ([self::VIEW_PAGES, self::EDIT_PAGES] as $ability) {
            Gate::define($ability, static fn (User $user): bool => Gate::forUser($user)->allows(AiConnectorServiceProvider::ABILITY));
        }
    }
}
