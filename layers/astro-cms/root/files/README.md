# Project

Static website (Astro, `frontend/`) with a headless CMS (Laravel + Filament, `backend/`). Editors work in the
admin at `/admin`; the public site is a static build that is regenerated and deployed by the **Publish** button.

| Path | What |
| --- | --- |
| `frontend/` | Astro 7 static site, Tailwind 4. Content comes from the CMS export at build time. |
| `backend/` | Laravel 13 + Filament 5: pages and site settings, the export API, the Publish button. |
| `docs/contract.md` | The contract between the two halves (versioned, guarded by a golden-file test). |
| `docker/nginx.example` | One domain: static site + php-fpm routing. |
| `Jenkinsfile` | One Jenkinsfile for the monorepo (`astroCmsPipeline`, see the header comment). |

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
editor ── Publish ─▶ PublishService ─▶ Jenkins job (CMS_PUBLISH_ID=<id>) ─▶ frontend build ─▶ deployStaticSite
   ▲  (Filament)      │ checks the export,    │                              │ GET /cms-api/v1/export             │
   │                  │ writes a `publishes`  │                              │ (bearer token, Zod-validated,       ▼
   │                  │ row (queued)          │                              │  /_cms media HEAD-checked)    web/current -> releases/<id>
   └──── status ◀──── POST /cms-api/v1/publishes/<id>/status (callback, bearer token) ◀── post { always }
```

1. An editor saves pages and settings in Filament. Nothing is public yet: the **Publish** page shows "unpublished changes".
2. **Publish** (`PublishService::publish()`): refuses when a publish is already queued or running, when Jenkins is not
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
- nginx: `docker/nginx.example`. The static release root and the backend volumes (`storage/app/cms-public`,
  `storage/app/cms-media`) must be readable by nginx.
- Staging: build the frontend with `PUBLIC_NOINDEX=true` (noindex meta, `Disallow: /`, no sitemap). Production leaves it unset.

## Notes

- `astroCmsPipeline` does not exist yet; the `Jenkinsfile` shows the configuration it is meant to take. Example names
  (`example-project`, `example.com`) must be replaced.
- One domain for the site and the admin (no separate admin subdomain). The admin panel assets (`/build`, `/css|js|fonts/filament`)
  are served by nginx from the backend `public/` directory, which has to be copied from the image after each deploy.
- The content model is deliberately minimal (pages with heading / text / image blocks, one settings record). Extend the
  exporter, the Zod schema, `Blocks.astro` and `docs/contract.md` together.
- Uploads of the admin are served from `/cms-media/` (images only, CSP sandbox) so that Filament can preview them.
