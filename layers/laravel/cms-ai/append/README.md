
## AI connector: CMS tools

The assistant that a person connects (see "AI connector") can work on pages. The tools live in `app/Infrastructure/Mcp/Tools/Cms/`; each extends `ConnectorTool`, calls an Application command or query and acts as the signed-in user.

| Tool | Scope | What it does |
| --- | --- | --- |
| `list_pages` | `content:read` | id, title, slug, published, updated_at, has_unpublished_changes |
| `get_page` | `content:read` | the page with its blocks |
| `list_block_types` | `content:read` | the block catalogue: fields, types, limits, required flags, allowed values |
| `list_revisions` | `content:read` | saved versions of a page (source, author, time) |
| `create_page` | `content:write` | a new page, always unpublished and not the home page |
| `update_page` | `content:write` | title, description, social image, blocks and the slug of an unpublished page; nothing is saved when nothing changes |
| `upload_image` | `content:write` | base64 image to the `cms-media` disk, returns the path for an image block or `og_image` |
| `restore_revision` | `content:write` | writes an earlier version back as a new version |

There is no tool for delete, publish, the home page, settings, users or form submissions, and `tests/Feature/CmsAi/CmsToolsAccessTest` pins the list. Every write is a version with the source `ai` (shown in the page history and in the list of changes on the Publish page); a tool refuses an input that does not match the block catalogue and returns the list of problems.

- **Policy**: the tools ask the gates `view-cms-pages` and `edit-cms-pages` (`CmsAiServiceProvider`), which follow `use-ai-connector` (whoever opens the admin). A project with roles redefines them with `Gate::define()`.
- **New block type**: add it to `BlockCatalogue::standard()`; `list_block_types` and the validation follow, no tool changes. A field of type image takes the path that `upload_image` returns.
- **Images**: png, jpeg, webp or avif, at most 10 MB and 8000 px a side, the bytes must be a real image. No URL fetching in this version (it needs an SSRF-safe fetcher: a separate decision).
- The client guide `docs/client/ai-connector.md` is Czech and matches these tools; fill in the placeholders (project name, domain) and keep it in step when a tool changes.
