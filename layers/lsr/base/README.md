# Layer `lsr/base`

Minimal LSR + RoadRunner + Inertia 3 (Vue 3) application, built on the `lsr-inertia` variant (`common` + `lsr/base`). The builder does not copy this file into the target project.

It is based on the layout of `esoul-internal` with the improvements of `code-hunt-game`: a Loader without JSON in exceptions, `UPLOAD_DIR` from the environment, `web` / `csrf` middleware groups, `tests/bootstrap.php` with `noDb`, and the newer `lsr/*` versions. Project specifics (mockups, leads, game, commerce, Elasticsearch, Redis, author headers) are stripped.

What the target project gets: `GET /up` (route `health`, `App\Http\Controllers\Health`) and `GET /` rendered by `App\Http\Controllers\Home` through Inertia into `assets/js/pages/Home.vue`, the Latte layout `templates/@layout.latte`, Vite 8 + Tailwind 4 + shadcn-vue (Button only), RoadRunner (`http`, `jobs`, `scheduler`; no SSR), the Docker image (`development` / `production` targets), dev and CI compose files and an `app:install` command that applies `config/migrations/migrations.neon`.

## Draft assumptions

- `lsr/*` versions are the current Packagist releases, equal to the `code-hunt-game` lock (`core` 0.7.0, `orm` 0.4.1, `routing` 0.5.1, `roadrunner` 0.1.19, `inertia` 0.1.9, `db` 0.3.17). `esoul-internal` (core 0.5.0) was not retested against them.
- Locks are not shipped (the builder renames the Composer package, which invalidates a `composer.lock` content hash; `pnpm-lock.yaml` would pin stale versions). The Dockerfile and `pnpm install --frozen-lockfile` need both locks, so run `composer install && pnpm install` and commit the locks right after creating a project.
- Debug mode comes from `[General] DEBUG` in `private/config.ini` (as in the reference projects) or, new, from the `APP_DEBUG` environment variable. `PRODUCTION` is its negation. Docker dev sets `APP_DEBUG=true`.
- The default `lsr.session` setup `init()` recurses into `App::getRequest()` while `App` is being built, so `config/di/extensions/lsr.neon` removes it (`setup!: []`). The file session then starts lazily, and the RoadRunner `HttpWorker` initializes it per request. Both reference projects replaced the session with a Redis one; no Redis is included here.
- Cache: Nette file storage with SQLite journal in `temp/` (no Redis). `ext-redis` stays required because `lsr/caching` requires it.
- No `lsr/auth`, `lsr/cqrs`, `lsr/otel`, SSR, text catalog or gettext/translation loading. `LsrExtension` still wires `Translations` (`cs_CZ` default), so the layout reads `translations->getLangId()`; `languages/` does not exist.
- `app:install` and `page_info` (key-value `App\Core\Info`) are kept because `docker/prepare-runtime.sh` runs the installer and `DbInstall` stores table versions there. `Seeder` and user types were removed. `migrations.neon` has no project tables.
- `scheduler` runs as a RoadRunner service although no jobs are registered; the RoadRunner `jobs` pool and `tasks` memory pipeline exist but no task handlers are registered.
- RoadRunner serves the compiled assets itself through the `static` middleware (`/var/www/public/build/assets` symlink created by the Dockerfile / `prepare-runtime.sh`), as in `esoul-internal`; nginx is only used for development (`docker/nginx/nginx.conf`: proxy to RoadRunner `:8001` and Vite `:5173`). `access_logs: true`, `max_request_size: 10` MiB are my defaults.
- `BrowserCsrfMiddleware` trusts `X-Forwarded-Proto` / `X-Forwarded-Host` / `Host`; the reverse proxy in front of RoadRunner must set them. It is simplified from `code-hunt-game` (no `ForwardedRequestUri`, no translated message).
- MariaDB `11.8` in both compose files (`esoul-internal` used 11.4 in CI and `latest` in dev; `code-hunt-game` 12.1.2 in production). Dev DB password is a throwaway default (`dev-db-password`, overridable by `DB_PASSWORD`), CI uses fixed CI-only values.
- PHPStan level 8 (`level 9` reports `mixed` casts in the copied `DbInstall`). PHP-CS-Fixer rules are `code-hunt-game`'s (`composer cs` / `composer cs:fix`; there is no `cbf` alias).
- Mutation testing: `infection/infection` (`^0.35`, as in `code-hunt-game`) with a generic `infection.json5` (source `src`, tmp and logs in `temp/tools/infection`, no ignores, no MSI threshold). `composer mutation` (changed lines against the local `main` branch) and `composer mutation:full` are local and optional, never a CI gate; both need a coverage driver (Xdebug or PCOV). The `code-hunt-game` split into a Paratest coverage run and an Infection run is not copied: Infection runs the suite itself.
- Health: `GET /up` is registered in `routes/10-public.php` outside every middleware group (no Inertia, no CSRF). It returns `200 {"status":"ok"}` while `SELECT 1` on `db.connection` succeeds and `503 {"status":"unavailable"}` otherwise, with `Cache-Control: no-store`. The check is the `App\Http\Health\DatabaseCheck` interface (autowired to `ConnectionDatabaseCheck` in `config/di/services-common.neon`), so tests use a double. The worker still starts the file session and sends `Set-Cookie` for this route (`lsr/roadrunner` `HttpWorker` does it for every request); no session file is written per call (checked: the `temp/` file count does not grow).
- Docker `HEALTHCHECK` (production stage) is `curl --fail http://127.0.0.1:8001/up` (`curl` is in the base image), interval 10 s, start period 30 s. `docker-compose.ci.yml` only tightens the timing and `up --wait` waits for it; `docker/http-smoke.php` checks `/up` first.
- Every route has `->name()` (`health`, `home`).
- `Jenkinsfile` calls the planned `lsrPipeline` with parameters copied from `laravelPipeline`; `images` / `deploy.stack` use the placeholder `lsr-app`, and the `smoke` key is a proposal (the step does not exist yet).
- Frontend: TypeScript strict mode, ESLint 10 flat config (`@typescript-eslint/no-explicit-any` enabled via the recommended TypeScript set; no `shadcn-vue` lint rules), Prettier plugins `organize-imports` and `tailwindcss`, Vitest with one unit test. `app.scss` and `sass-embedded` are dropped; `assets/css/tailwind.css` is the only stylesheet. Icons (`lucide`) are not a dependency yet; add `@lucide/vue` when shadcn components need it.
- `docker/http-smoke.php` checks the Inertia HTML and JSON responses, the compiled asset URLs and that private paths return 404.
- The `development` target copies Node into the PHP image (as `esoul-internal` does); `startDev.sh` runs `pnpm install --no-frozen-lockfile`.
- `App::run()` is overridden (as in `esoul-internal`): `lsr/core` otherwise `readfile()`s any existing non-PHP file below the application root (e.g. `config/di/services.neon`) and calls `exit()`, which also kills the worker. RoadRunner's `static` middleware serves the public assets instead; everything else answers 404. Request paths ending in `.php` are routed like the path without it (core behaviour), they never serve file contents.

## Verified

`bin/templates build lsr-inertia --out=<tmp>` (`esoul/guidelines` removed from `composer.json`, it is unpublished), then in the build: `composer install`, `vendor/bin/phpunit` (15 tests), `composer phpstan`, `composer cs`, `php bin/console list`, `php bin/console routes:cache:compile`, `pnpm install`, `pnpm run eslint` (also confirmed to reject an explicit `any`), `pnpm run tsc`, `pnpm run test`, `pnpm run build`, `pnpm run format:check`, `composer mutation:full` (72 mutants, covered MSI 66 %, exit code 0) and `composer mutation` in a git checkout with a `main` branch. Docker: `docker build -f docker/Dockerfile --target production`, then `docker compose -f docker-compose.ci.yml up --wait` with `APP_IMAGE` (the `app` container reported `healthy` from the image `HEALTHCHECK`) and `docker compose -f docker-compose.ci.yml --profile verification run --rm http-smoke` passed against RoadRunner + MariaDB 11.8 (migrations ran, scheduler service started); with the database stopped `/up` answered `503`. The `development` image builds and the development compose stack was validated with `docker compose config`, but `docker compose up` (Vite + HMR) was not run.

## Open questions

1. SSR as an opt-in layer (`assets/js/ssr.ts`, `build:ssr`, `.rr.yaml` `service.ssr`, Node in the production image) or hand-edited per project?
2. `lsr/auth` (login, CSRF, `LoggedIn`/`LoggedOut` groups) as a feature layer?
3. `lsr/cqrs` + `src/CQRS/` skeleton and the async task handler, `lsr/otel`, and a Redis profile (session, cache, scheduler lock) as add-on layers?
4. Mirror or fork `Heroyt/lsr-*` under the eSoul GitHub organisation?
5. Which MariaDB image tag is canonical?
