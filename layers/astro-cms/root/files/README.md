# Project

Static website (Astro, `frontend/`) with a headless CMS (Laravel + Filament, `backend/`). Editors work in the
admin at `/admin`; the public site is a static build that is regenerated and deployed by the **Publish** button.

| Path | What |
| --- | --- |
| `frontend/` | Astro 7 static site, Tailwind 4. Content comes from the CMS export at build time. |
| `backend/` | Laravel 13 + Filament 5: pages and site settings, the export API, the Publish button. |
| `docs/contract.md` | The contract between the two halves (versioned, guarded by a golden-file test). |
| `docker/nginx.example` | Static site + php-fpm routing, for both admin placements (same domain `/admin` or `admin.<domain>`). |
| `Jenkinsfile` | One Jenkinsfile for the monorepo (`astroCmsPipeline`, see the header comment). |

## Where the admin lives

The CMS supports two placements; the template defaults to the first. Both are written out in `docker/nginx.example`
(Mode A active, Mode B commented) and differ only in nginx and a few environment values, never in code.

| | Same domain (Mode A, default) | Subdomain (Mode B) |
| --- | --- | --- |
| Admin URL | `https://example.com/admin` | `https://admin.example.com/admin` |
| nginx | one server block: static site + backend routes | site block (static + `/_cms/`), plus an `admin.` block that proxies `/admin`, `/livewire*`, `/cms-api`, `/up` to php-fpm and serves `/cms-media/`, `/_cms/` and the panel assets |
| `APP_URL` (backend) | `https://example.com` | `https://admin.example.com` |
| `SESSION_DOMAIN` | `null` | `null` (host-only cookie; never `.example.com`) |
| `cmsApiUrl` (Jenkinsfile) / `CMS_API_URL` | `https://example.com/cms-api/v1` | `https://admin.example.com/cms-api/v1` |
| `siteUrl` | `https://example.com` | `https://example.com` |
| CORS | not needed | not needed |

Choose the **same domain** when one hostname and one certificate are enough: simplest, one server block. Choose the
**subdomain** when the admin should be separable from the public site: its own cookies and CSP, its own access rules
(VPN or IP allowlist on `admin.` without touching the site), and a public site that exposes no PHP routes at all.

- CORS is never needed: the static build fetches the export server-side (Node, in Jenkins) and visitors' browsers never call
  the backend.
- `CMS_API_ALLOWED_IPS` has to contain the build agent in both modes (see the setup checklist).
- In Mode B the build HEAD-checks media on the origin of `CMS_API_URL`, so the admin host serves `/_cms/` as well; the site
  domain serves it too because the HTML refers to `/_cms/<hash>` relatively.
- Switching later means changing nginx, `APP_URL`, `cmsApiUrl` and the DNS record; the exported media URLs are relative (`/_cms/<hash>`).

## Local development

```sh
# backend (see backend/README.md for the full setup)
cd backend && composer install && cp .env.example .env && php artisan key:generate && php artisan migrate
php artisan make:filament-user                      # admin at http://127.0.0.1:8000/admin
# set CMS_EXPORT_TOKEN=<random string> in backend/.env, then:
php artisan serve

# frontend
cd frontend && pnpm install
CMS_SOURCE=fixture pnpm dev                         # no backend needed
# or against the running backend: CMS_API_URL=http://127.0.0.1:8000/cms-api/v1, CMS_API_TOKEN=<CMS_EXPORT_TOKEN>
```

## Publish flow

```text
editor ── Publish ─▶ StartPublishCommand ─▶ Jenkins job (CMS_PUBLISH_ID=<id>) ─▶ frontend build ─▶ deployStaticSite
   ▲  (Filament)      │ checks the export,    │                              │ GET /cms-api/v1/export             │
   │                  │ writes a `publishes`  │                              │ (bearer token, Zod-validated,       ▼
   │                  │ row (queued)          │                              │  /_cms media HEAD-checked)    web/current -> releases/<id>
   └──── status ◀──── POST /cms-api/v1/publishes/<id>/status (callback, bearer token) ◀── post { always }
```

1. An editor saves pages and settings in Filament. Nothing is public yet: the **Publish** page shows "unpublished changes".
2. **Publish** (`StartPublishCommand`): refuses when a publish is already queued or running, when Jenkins is not
   configured, or when the export has problems (the same rules as the Zod schema). Otherwise it stores a `publishes` row
   (`queued`) and calls `{CMS_JENKINS_JOB_URL}/buildWithParameters` with `CMS_PUBLISH_ID`.
3. The Jenkins build fetches `GET /cms-api/v1/export` (bearer `CMS_EXPORT_TOKEN`). While serving it, the backend copies
   every referenced image to `storage/app/cms-public/<sha256[0:20]>.<ext>`; nginx serves it at `/_cms/`. The build
   validates the export with Zod, HEAD-checks the media, builds, runs `pnpm verify` and deploys `dist/` as a new release.
   **In CI the build fails when the export is unreachable or invalid**: it never falls back to the fixture or to old data.
4. `post { always }` reports `{result, buildUrl, contentHash}` to `POST /cms-api/v1/publishes/<id>/status`
   (bearer `CMS_JENKINS_CALLBACK_TOKEN`). `SUCCESS` and `UNSTABLE` mean deployed.
5. The callback is best effort. **Refresh status** polls Jenkins (queue item, then build), and a publish without a result
   after `CMS_PUBLISH_TIMEOUT` minutes (default 30) is marked failed so the button cannot stay blocked. A late callback
   is still recorded, with a note.

Media: names are content addressed, so files are immutable and cacheable for a year. `cms:prune-public-media` (daily 03:30)
deletes files that no build of the last 7 days referenced.

### Setup checklist

- Backend `.env`: `CMS_EXPORT_TOKEN`, `CMS_JENKINS_JOB_URL` (`https://<jenkins>/job/<job>`), `CMS_JENKINS_USER`,
  `CMS_JENKINS_API_TOKEN`, `CMS_JENKINS_CALLBACK_TOKEN`, `CMS_API_ALLOWED_IPS`.
- Jenkins credentials (Secret Text): the export token (= `CMS_EXPORT_TOKEN`) and the callback token
  (= `CMS_JENKINS_CALLBACK_TOKEN`), named in the `Jenkinsfile`.
- **`CMS_API_ALLOWED_IPS` has to contain the address of the build agent.** The build fetches `/cms-api` over the public
  URL, so a changed agent address makes the export (and so every publish) answer 403 until the list is updated.
- nginx: `docker/nginx.example` (pick Mode A or B first). The static release root and the backend volumes (`storage/app/cms-public`,
  `storage/app/cms-media`) must be readable by nginx.
- Staging: build the frontend with `PUBLIC_NOINDEX=true` (noindex meta, `Disallow: /`, no sitemap). Production leaves it unset.

## Notes

- `astroCmsPipeline` does not exist yet; the `Jenkinsfile` shows the configuration it is meant to take. Example names
  (`example-project`, `example.com`) must be replaced.
- The admin sits on the site's domain under `/admin` by default or on `admin.<domain>` (see "Where the admin lives"). The admin
  panel assets (`/build`, `/css|js|fonts/filament`) are served by nginx from the backend `public/` directory, which has to be
  copied from the image after each deploy.
- The content model is deliberately minimal (pages with heading / text / image blocks, one settings record). Extend the
  exporter, the Zod schema, `Blocks.astro` and `docs/contract.md` together.
- Uploads of the admin are served from `/cms-media/` (images only, CSP sandbox) so that Filament can preview them.
