# Changelog

Every change to the template source (`layers/`, `variants/`, the builder) is recorded here, **newest first**, one entry per change. An
entry names the **affected variants and layers** and carries **migration notes** for projects created earlier.

New entries are written under `## Unreleased` as `### YYYY-MM-DD: short title`. The date is stamped when the change is merged (a commit
made directly to `main`: the commit date); a PR adds its entry with the date of the day and the merge corrects it when it differs. The
template has no releases, so the section only grows; a release would turn it into the version heading. Entries below it, from before
the section existed, are `## YYYY-MM-DD: short title`.

There are no version tags: the `main` HEAD of each variant repository is the latest template, and a project records the app-templates
source commit it was built from in `.esoul/template.json` (`{"variant": ..., "commit": ...}`). The update skill reads the entries newer
than that commit (see the README, "Updating existing projects").

Entry format:

```markdown
### YYYY-MM-DD: short title

Affects: <variants> / <layers>

What changed, in one or two sentences.

Migration: what an existing project must do (or "none").
```

## Unreleased

### 2026-11-23: Guidelines 0.3 and the Lunar guideline layers

Affects: `laravel-inertia`, `laravel-filament`, `laravel-inertia-filament`, `laravel-lunar`, `laravel-cms`, `lsr-inertia`, `astro-cms` / layers `laravel/base`, `lsr/base`, `astro-cms/root`, `laravel/lunar`; the builder.

The builder renders with `esoul-cz/guidelines` `^0.3` (v0.3.0), and the layers require it in `require-dev`, so `composer update` keeps the
rendered docs and the installed installer in step. `laravel-lunar` declares `package/filament` (Lunar owns a Filament panel) and the new
`package/lunar` (`merge/esoul-guidelines.json`), so its `docs/project_guidelines/` gain the Lunar sections of architecture, testing and
integrations.

Migration: in `composer.json` raise `esoul-cz/guidelines` to `^0.3`, run `composer update esoul-cz/guidelines` (its `post-update-cmd` runs
`esoul-guidelines sync`) and commit the result. A `laravel-lunar` project that is still on `^0.2` adds `package/filament` and
`package/lunar` to `esoul-guidelines.json` first.

### 2026-11-23: `laravel-lunar` calculates cart lines in integer minor units; the "no Lunar guideline layer" note is dropped

Affects: `laravel-lunar` / layer `laravel/lunar` (`config/lunar/cart.php`, `app/Infrastructure/Lunar/Cart/CalculateLines.php`, `tests`, `README.md`, `layer.json`).

`config/lunar/cart.php` replaces Lunar's `CalculateLines` cart pipeline step with `App\Infrastructure\Lunar\Cart\CalculateLines`: a line's sub total is `value * quantity / unitQty` in integer minor units, rounded once half away from zero (`Money::proportion()`, `Money::ROUNDING`). Lunar's step multiplies floats and truncates, so the two differ only when the unit quantity does not divide `value * quantity` (2.5 minor units: Lunar 2, the project 3). Tests pin the project step against Lunar's on amounts where they cannot differ, and pin the `cart` pipeline to Lunar's default with only that step replaced, so a Lunar update that changes the steps fails the suite. A shop currency must be a case of `App\Domain\ValueObjects\Currency`. The "no Lunar guideline layer yet" note of the README and the layer description is gone: `package/lunar` is written (its declaration in `esoul-guidelines.json` comes with guidelines 0.3).

Migration: copy the config, the step and its tests, make sure the shop's currencies are `Currency` cases, and remove the note from the README. Existing orders are unaffected; only carts that calculate lines with a unit quantity above 1 can round differently.

### 2026-11-23: `laravel-lunar` registers Lunar's panel in its own provider

Affects: `laravel-lunar` / layer `laravel/lunar` (`app/Domain/Providers/Filament/LunarPanelProvider.php`, `bootstrap/providers.php`, `app/Domain/Providers/AppServiceProvider.php`).

`LunarPanel::panel(...)->register()` moved from `AppServiceProvider` into `App\Domain\Providers\Filament\LunarPanelProvider`, listed in `bootstrap/providers.php` after `AppServiceProvider` (the layer no longer overrides `AppServiceProvider`, which is the base's empty one again). Lunar keeps its default theme; a brand logo or render hooks go into the panel callback of the provider.

Migration: create `LunarPanelProvider` with the registration, add it to `bootstrap/providers.php` and remove the registration from `AppServiceProvider`.

### 2026-11-23: `Money::proportion()`

Affects: `laravel-inertia`, `laravel-filament`, `laravel-inertia-filament`, `laravel-lunar` (and the backend of `laravel-cms`) / layer `laravel/base` (`app/Domain/ValueObjects/Money.php`).

`Money::proportion($numerator, $denominator)` takes an exact fraction of an amount (a pack price bought in quantities) and rounds once with `Money::ROUNDING`; the division keeps 40 digits so no float and no early rounding is involved.

Migration: optional; copy the method (and its tests) from the template's `Money`.

### 2026-11-23: Laravel variants write a JUnit log of the PHP tests

Affects: `laravel-inertia`, `laravel-filament`, `laravel-inertia-filament`, `laravel-lunar` (and the backend of `laravel-cms`) / layer `laravel/base` (`phpunit.xml`, `.gitignore`, `.dockerignore`).

`phpunit.xml` logs JUnit to `.ci/junit/phpunit.xml` (also under `composer test` and Paratest), so the shared pipeline can publish per-test times (jenkins-libraries#8). `.ci/` is gitignored and kept out of the image build context.

Migration: add `<logging><junit outputFile=".ci/junit/phpunit.xml"/></logging>` to `phpunit.xml`, `/.ci/` to `.gitignore` and `.ci` to `.dockerignore`.

### 2026-11-23: LSR writes a JUnit log and the `Jenkinsfile` names the `lsrPipeline` call

Affects: `lsr-inertia` / layer `lsr/base` (`phpunit.xml`, `append/.gitignore`, `Jenkinsfile`).

`phpunit.xml` has `<logging><junit outputFile=".ci/junit/phpunit.xml"/></logging>`, so the shared pipeline can read per-test times (jenkins-libraries#8), and `.ci/`
is gitignored. The marked comment of the composed `Jenkinsfile` now holds the exact one-call replacement planned in jenkins-libraries#10 (`image`, `smoke`, `deploy`;
the step does not accept `images`); the file still runs the composed steps until that step is released.

Migration: add the `<logging>` block to `phpunit.xml` and `/.ci/` to `.gitignore`; keep the `Jenkinsfile` until `lsrPipeline` exists.

### 2026-11-23: LSR runs PHPStan at level 10

Affects: `lsr-inertia` / layer `lsr/base` (`phpstan.neon`, `include/constants.php`, `src/Core/Info.php`, `src/Install/DbInstall.php`, `src/Http/Templates/AutoFillParameters.php`).

`level: 8` became `level: 10` with no baseline. The 10 errors the shipped code had are fixed in the code: the environment, database and cache values are
checked before they are used (`UPLOAD_DIR`, the foreign-key setting, the index names and foreign-key names read from the database), the cache tag key is the
`Cache::Tags` constant, `getTableNameFromClass()` uses `is_subclass_of()` and `AutoFillParameters` takes any array key and copies only string keys.

Migration: set `level: 10` in `phpstan.neon`, run `composer phpstan` and fix what it reports the same way (no `@phpstan-ignore`, no baseline); copy the fixes
above into the files you did not change.

### 2026-11-23: LSR application clock is UTC

Affects: `lsr-inertia` / layer `lsr/base` (`include/constants.php`, `include/load.php`, `config/di/environment.php`, `tests/Config/EnvironmentTest.php`).

`include/load.php` set `Europe/Prague` as the PHP time zone, against the rule that the application clock stays UTC. The constant `APP_TIMEZONE` (`UTC`) in
`include/constants.php` now sets it, and the database session is pinned to `+00:00` through the PDO init command in `config/di/environment.php`, so `NOW()` and
`TIMESTAMP` columns agree with PHP and the setting survives a reconnect. `lsr/db` and `lsr/orm` have no time-zone handling of their own. The business time zone
goes into `CONTEXT.md` and is applied only where a date is shown or a calendar boundary is cut.

Migration: new applications need nothing. An existing application keeps `Europe/Prague` until it migrates, because its stored `DATETIME` values are local
wall-clock times: to migrate, take the three files above, convert the stored `DATETIME` values (`CONVERT_TZ(column, 'Europe/Prague', 'UTC')`; `TIMESTAMP` columns
need no conversion), and apply the business time zone where dates are displayed.

### 2026-11-23: LSR serves FastCGI and keeps the jobs queue in boltdb

Affects: `lsr-inertia` / layer `lsr/base` (`.rr.yaml`, `docker/nginx/nginx.conf`, `docker/Dockerfile`, `docker/prepare-runtime.sh`, `docker/http-smoke.php`, `docker-compose.ci.yml`).

`.rr.yaml` has the FastCGI listener (`http.fcgi.address: tcp://0.0.0.0:9000`) next to the HTTP listener on 8001, which stays for the health check and the smoke
test. The `tasks` pipeline uses the `boltdb` driver with the file `/app/temp/queue/tasks.db`, inside the persistent `./temp` mount, so queued tasks survive a
restart; `cache:clean`, the other cache commands and `prepare-runtime.sh` do not touch `temp/queue/`. The dev NGINX uses `fastcgi_pass app:9000` with the production
parameters (`HTTP_HOST`, `HTTPS`, `X-Forwarded-*` replaced), so development exercises the production path, and `docker/http-smoke.php` sends FastCGI requests to
`/up` and `/` (`HTTP_SMOKE_FASTCGI_ADDRESS`, set in `docker-compose.ci.yml`).

Migration: copy the changes of the files above. In a deployed stack the run directory's `.rr.yaml` is mounted over the image's: give it the `fcgi` listener (if
it has none) and the `boltdb` pipeline with the same file path, and create `./temp/queue` there; tasks that are only in the old `memory` queue are lost at the
switch. Never delete `temp/queue/` together with the caches.

### 2026-11-23: LSR CSRF check does not read `X-Forwarded-*`

Affects: `lsr-inertia` / layer `lsr/base` (`src/Http/Middleware/BrowserCsrfMiddleware.php`, `tests/Http/BrowserCsrfMiddlewareTest.php`).

Checked against RoadRunner over FastCGI: the middleware worked behind NGINX, but it preferred `X-Forwarded-Proto` and `X-Forwarded-Host` over the request, so a
client could forge them whenever the proxy did not replace them (a plain `include fastcgi_params` does not). It now takes the public origin from the request itself:
the scheme of the request URI and the `Host` header, which RoadRunner builds from the `HTTPS` and `HTTP_HOST` FastCGI parameters.

Migration: replace the middleware and its test. The NGINX in front of the application must pass `HTTPS` and `HTTP_HOST` (the host's `lsr-fastcgi.conf` and the dev
`nginx.conf` do); a proxy that terminates TLS and talks plain HTTP to RoadRunner would now fail the origin check.

### 2026-11-23: LSR HTTP client service and a network guard in the tests

Affects: `lsr-inertia` / layer `lsr/base` (`composer.json`, `phpunit.xml`, `config/di/services-common.neon`, `src/Http/Client`, `tests/Support`, `tests/Fixtures`, new tests).

The outgoing HTTP client is the DI service `http.client` (`App\Http\Client\CurlHttpClient`, PSR-18, typed `Psr\Http\Client\ClientInterface`), and a test replaces
it with `Tests\Support\Http\FakeHttpClient::install(App::getContainer())`. The PHPUnit extension `Tests\Support\Network\NetworkGuardExtension` (registered in
`phpunit.xml`) makes every `http(s)` stream request to a host other than localhost throw; raw sockets and cURL are not intercepted. `phpunit.xml` excludes the
groups `sandbox` and `quarantine`. `composer test` and `test:parallel` no longer set `XDEBUG_MODE=coverage`; `composer test:coverage` does. `ext-curl` is required.

Migration: copy `src/Http/Client`, `tests/Support`, `tests/Fixtures`, the `http.client` service and the new tests, add the `<groups>` and `<extensions>` blocks to
`phpunit.xml`, `ext-curl` to `composer.json` and the three scripts, and replace direct HTTP calls in the code by a service that depends on `ClientInterface`.

### 2026-11-23: LSR frontend tree, scripts and checks follow the Inertia Vue guideline

Affects: `lsr-inertia` / layer `lsr/base` (`assets/js`, `components.json`, `eslint.config.js`, `vitest.config.ts`, `.prettierignore`, `package.json`).

`assets/js/components/ui` moved to `assets/js/design-system/ui`, `assets/js/lib/utils.ts` (and its test) to `assets/js/utils/classes.ts`, and a curated
`assets/js/design-system/index.ts` exports `AppButton`; `Home.vue` imports it from `@/design-system`. `components.json` aliases point there (`utils` is
`@/utils/classes`, `ui` is `@/design-system/ui`, the `lib` alias is gone). ESLint restricts imports (application code must not import `@/design-system/ui`;
the design system must not import Inertia, pages, components or layouts), `vitest.config.ts` has the `shared`/`isolated` project split of the Laravel template, and the
`lint` script became `eslint:fix`, so the scripts are `eslint`, `eslint:fix`, `format:check`, `format`, `tsc`, `build` and `test`.

Migration: move the folders and fix the imports (`@/lib/utils` becomes `@/utils/classes`, `@/components/ui/...` becomes `@/design-system/ui/...` inside the design
system and an `App*` export of `@/design-system` elsewhere), update `components.json`, `.prettierignore` and the ESLint ignore path, copy the two
`no-restricted-imports` blocks and the Vitest config, rename `lint` to `eslint:fix` and update scripts or CI that call it.

### 2026-11-23: LSR pnpm install hardening and `pnpm audit` in CI

Affects: `lsr-inertia` / layer `lsr/base` (`pnpm-workspace.yaml`, `Jenkinsfile`).

`pnpm-workspace.yaml` gets `minimumReleaseAge: 1440` and `auditConfig.ignoreGhsas` (the existing `allowBuilds` stays). `pnpm audit` runs in CI. Five advisories in
dev-only packages (Vite's optional `stylus` peer and the shadcn-vue CLI) cannot be fixed yet and are listed with the reason and the review date 2026-12-23.

Migration: add the two settings to `pnpm-workspace.yaml`, run `pnpm audit` and fix or list (with reason and review date) what it reports.

### 2026-11-23: LSR `Jenkinsfile` is composed from the existing steps

Affects: `lsr-inertia` / layer `lsr/base` (`Jenkinsfile`).

`lsrPipeline` does not exist yet (plan J4), so the file composes the library steps that do: `fullPhpTest` (PHPStan, `composer cs`, tests, `composer audit`),
`fullNodeTest` (ESLint, Prettier, `tsc`, Vitest, `pnpm audit`, build), a local build of the production image with `dockerComposeSmokeTest`, then
`dockerBuildMultiArch` and `deployKomodoStack` from `main` and `master`. A comment names the planned one-call `lsrPipeline`; set `image` and `stack` in the file.

Migration: replace the `Jenkinsfile` and set `image` (and `stack` to deploy). When `lsrPipeline` is released, the file becomes that one call.

### 2026-11-23: LSR guidelines 0.2, sync on `composer update`, no Satis, MariaDB 12.3, LFS carve-out and an operations README

Affects: `lsr-inertia` / layer `lsr/base` (`merge/composer.json`, `merge/esoul-guidelines.json`, `append/.gitattributes`, `docker-compose.yml`, `docker-compose.ci.yml`, `README.md`).

`esoul-cz/guidelines` is `^0.2`, and `composer.json` has `"post-update-cmd": ["esoul-guidelines sync"]`. The guideline layers now include `package/mutation-testing`
(the template requires `infection/infection`). The `repositories` entry for `packages.esoul.cz` is gone: every dependency resolves from Packagist. The dev and CI
MariaDB is `12.3`, the series of the production server. `.gitattributes` re-includes `assets/js/**` and `assets/css/**` out of Git LFS, where `common` sends
`assets/**` (the Vite sources live there). The application gets an operations `README.md` (ports, commands, the persistent paths `./upload`, `./logs`, `./temp`
and `./temp/queue/tasks.db`).

Migration: `composer require --dev esoul-cz/guidelines:^0.2`, add the `post-update-cmd`, add `package/mutation-testing` to `esoul-guidelines.json` and run
`esoul-guidelines sync`; remove the `repositories` entry and run `composer update --lock`; use `mariadb:12.3` in both compose files; append the two lines to
`.gitattributes` (files already committed to LFS stay there until `git lfs migrate export --include="assets/js/**,assets/css/**"`); write the operations README.
### 2026-11-23: The Laravel skeleton is re-synced

Affects: layer `laravel/skeleton` (no variant output changes).

`bin/templates skeleton:sync` strips `tests/TestCase.php`, which `laravel/base` overwrites anyway (the committed skeleton still had it).

Migration: none.

### 2026-11-23: `laravel-lunar` is published with a "no Lunar guideline layer yet" note

Affects: `laravel-lunar` / layer `laravel/lunar` (`layer.json`, `README.md`).

There is no `package/lunar` guideline layer (it is written from the first Lunar project, Indcom, plan G3); `esoul-guidelines` skips the detected layer. The project README and the layer description say so and state what applies until then: the Laravel rules, with Lunar specifics decided in ADRs. The variant is otherwise unchanged (Lunar 1.x with its Filament 4 panel).

Migration: none.

### 2026-11-23: Filament variants: a generated panel theme

Affects: `laravel-filament`, `laravel-inertia-filament` (and the backend of `laravel-cms`) / layers `laravel/filament` (`resources/css/filament/admin/theme.css`, `AdminPanelProvider`), `laravel/base` and `laravel/inertia-vue` (`vite.config.*`, `tests/TestCase.php`).

The admin panel is themed as the Filament guideline asks: `resources/css/filament/admin/theme.css` (generated with `php artisan make:filament-theme admin --pm=pnpm`, with the `@source` of `app/Infrastructure/Filament`) is a Vite entry and registered with `viteTheme()`. Both Vite configs build every `resources/css/filament/*/theme.css` they find, so a second panel's theme needs no config change. `laravel/base` now ships its own `vite.config.js` (the upstream one plus that glob), and `tests/TestCase.php` calls `withoutVite()` for every variant (the PHP tests run before the assets are built).

Migration: copy the theme file, add `->viteTheme('resources/css/filament/admin/theme.css')` to the panel, add `...globSync('resources/css/filament/*/theme.css')` to the `input` of the Vite config (`import { globSync } from 'node:fs'`), call `$this->withoutVite()` in `TestCase::setUp()`, and run `pnpm install && pnpm build`.

### 2026-11-23: `laravel/inertia-vue`: script names, Wayfinder form variants, preload cap

Affects: `laravel-inertia`, `laravel-inertia-filament`, `laravel-lunar` / layer `laravel/inertia-vue` (`package.json`, `vite.config.ts`, `bootstrap/app.php`, `components.json`).

The `lint` script (an alias of `eslint`) is removed; the scripts are `eslint`, `eslint:fix`, `format:check`, `format`, `tsc`, `build` and `test`. The Wayfinder plugin runs with `formVariants: true`, matching `wayfinder:generate --with-form` of the production image. `bootstrap/app.php` caps the preload `Link` headers with `AddLinkHeadersForPreloadedAssets::using(limit: 25)` (a safety net against oversized FastCGI response headers, not a budget). The shadcn `lib` alias is dropped from `components.json`; `utils` already points at `@/utils/classes`.

Migration: delete the `lint` script (use `pnpm eslint`), pass `{ formVariants: true }` to `wayfinder()` in `vite.config.ts`, add the middleware to `$middleware->web(append: [...])`, remove `"lib": "@/lib"` from `components.json`.

### 2026-11-23: Laravel variants: local-only seed data, UTC for plain PHP, LFS assets out of the build context, pinned Mailpit

Affects: `laravel-inertia`, `laravel-filament`, `laravel-inertia-filament`, `laravel-lunar` (and the backend of `laravel-cms`) / layer `laravel/base` (`database/seeders`, `docker/*/php.ini`, `.dockerignore`, `docker-compose.yml`, `docker/production/Dockerfile`).

`DatabaseSeeder` no longer creates `test@example.com` everywhere: that account lives in a new `DemoSeeder`, which `DatabaseSeeder` calls only when the application runs in the `local` environment. `date.timezone` of both `php.ini` files is `UTC` (it was `Europe/Prague`; Laravel's `app.timezone` was already UTC). `.dockerignore` excludes `assets` (Git LFS client material). The production Dockerfile runs `wayfinder:generate --with-form` and its header says that nginx runs on the host (the image has no web server). Mailpit is pinned to `axllent/mailpit:v1.34` and the MariaDB tag `12.3` is documented as the production series (`mariadb_series` of the ansible role).

Migration: move any seeded account into a local-only seeder, set `date.timezone=UTC` in both `php.ini` files (apply the business time zone only where a date is shown), add `assets` to `.dockerignore`, use `--with-form` in the Wayfinder step of the Dockerfile, and pin the Mailpit image. A project on another MariaDB series than the server keeps its own tag until it migrates.

### 2026-11-23: Laravel variants: an operations README, port 8080 in `.env.example`, OTEL placeholders

Affects: `laravel-inertia`, `laravel-filament`, `laravel-inertia-filament`, `laravel-lunar` (and the backend of `laravel-cms`) / layer `laravel/base` (`README.md`, `.env.example`).

The project README is an operations README (requirements, setup, ports 8080 / 5173 / 8025, commands, configuration, troubleshooting) instead of the Laravel marketing text of the skeleton. `.env.example` defines `APP_PORT=8080` first and builds `APP_URL` from it (it was `http://localhost:8000`, a port nothing serves). It also carries the `OTEL_*` placeholders for the OTLP export to the host collector (`OTEL_PHP_AUTOLOAD_ENABLED=false`: the `opentelemetry` PHP extension is in the base image, the SDK is added by the project that switches it on; the README says how).

Migration: replace the README with the project's own operations README (the template's is a starting point), set `APP_URL` to `http://localhost:${APP_PORT}` after `APP_PORT=8080`, and add the `OTEL_*` block to `.env.example`.

### 2026-11-23: Laravel variants: the image healthcheck also requests `/up`

Affects: `laravel-inertia`, `laravel-filament`, `laravel-inertia-filament`, `laravel-lunar` (and the backend of `laravel-cms`) / layer `laravel/base` (`docker/production/Dockerfile`, `docker/production/healthcheck`).

The healthcheck still reports a Supervisor program that is not `RUNNING`, and now also sends a FastCGI request for `/up` straight to php-fpm (`cgi-fcgi` from the apk package `fcgi`, installed in the `base` stage next to Supervisor) and fails unless it answers `200`: the deployment guideline's "the image's HEALTHCHECK requests `/up`", with no web server in the image. The request shows in the php-fpm access log every 30 seconds.

Migration: add `fcgi` to the `apk add` line of the `base` stage and replace `docker/production/healthcheck` with the template's.

### 2026-11-23: Laravel variants: a Money value object, cast and formatter

Affects: `laravel-inertia`, `laravel-filament`, `laravel-inertia-filament`, `laravel-lunar` (and the backend of `laravel-cms`) / layer `laravel/base` (`app/Domain/ValueObjects`, `app/Infrastructure/Casts/MoneyCast.php`, `config/money.php`, `tests`, `composer.json`, `.env.example`).

`App\Domain\ValueObjects\Money` (integer minor units, a `Currency` backed enum with exponents, add, subtract, multiply with one rounding mode, `allocate`, comparisons, overflow as `ArithmeticError`), `MoneyFormatter` (integer and string arithmetic only, no float) and `App\Infrastructure\Casts\MoneyCast` (a `*_currency` sibling column, `config('money.default_currency')` as the fallback, `MONEY_DEFAULT_CURRENCY`) make the money rules of the guidelines hold out of the box. It is an own class, not `moneyphp/money`, because the rules forbid the float and numeric-string entry points of a general library (reasons in `layers/laravel/base/README.md` and the class comment). `ext-bcmath` is required.

Migration: optional. Copy the files above to adopt it; a project that already has its own money type keeps it (and its money ADR).

### 2026-11-23: Laravel variants: `TRUSTED_PROXIES` is wired

Affects: `laravel-inertia`, `laravel-filament`, `laravel-inertia-filament`, `laravel-lunar` (and the backend of `laravel-cms`) / layers `laravel/base` (`bootstrap/app.php`, `config/proxy.php`, `app/Infrastructure/Http/Middleware/TrustProxies.php`, `.env.example`) and `laravel/inertia-vue` (`bootstrap/app.php`).

The deployment guideline names a `TRUSTED_PROXIES` setting, but nothing read it. `App\Infrastructure\Http\Middleware\TrustProxies` now reads `config('proxy.trusted')` (fed by `TRUSTED_PROXIES`: addresses or CIDR ranges, comma-separated) and replaces the framework middleware in `bootstrap/app.php`. Empty trusts nobody, which is what the host NGINX with FastCGI needs; `*` is refused. A feature test checks that forwarded headers from any other address are ignored.

Migration: copy `TrustProxies.php`, `config/proxy.php` and `tests/Feature/Http/TrustProxiesTest.php`, add `$middleware->replace(\Illuminate\Http\Middleware\TrustProxies::class, TrustProxies::class)` to `withMiddleware()` in `bootstrap/app.php`, and add `TRUSTED_PROXIES=` to `.env.example`. A stack behind the host NGINX sets nothing; a stack behind another proxy lists its address.

### 2026-11-23: Laravel variants: hardened pnpm installs, unfiltered audit, pnpm in the setup script

Affects: `laravel-inertia`, `laravel-filament`, `laravel-inertia-filament`, `laravel-lunar` / layers `laravel/base` (`pnpm-workspace.yaml`, `.npmrc`, `composer.json`, `Jenkinsfile`) and `laravel/inertia-vue` (`Jenkinsfile`).

`pnpm-workspace.yaml` carries the install hardening of the dependencies guideline: `minimumReleaseAge: 1440`, an empty `allowBuilds` and an empty `auditConfig.ignoreGhsas` (the `ignoreScripts: true` it replaces is gone). `.npmrc` no longer sets `ignore-scripts` and `audit` (pnpm 12 ignores them). The `setup` Composer script runs `pnpm install` and `pnpm build` instead of npm. The Jenkinsfile runs the unfiltered `pnpm audit` as `nodeStaticChecks`, not only critical advisories.

Migration: replace the keys of `pnpm-workspace.yaml` as above (`allowBuilds` lists any package that needs an install script; an ignored advisory goes into `ignoreGhsas` with a reason and a review date), strip the two keys from `.npmrc` (keep the file, the production Dockerfile copies it), make `scripts.setup` a string that points at a `setup:steps` list using `pnpm install` and `pnpm build`, and add `nodeStaticChecks: [[name: 'Dependency audit', command: 'pnpm audit']]` to `laravelPipeline`.

### 2026-11-23: Laravel variants: guidelines 0.2 with their package layers, synced on `composer update`

Affects: `laravel-inertia`, `laravel-filament`, `laravel-inertia-filament`, `laravel-lunar` / layers `laravel/base` (`composer.json`), `laravel/inertia-vue` and `laravel/filament` (`esoul-guidelines.json`).

`esoul-cz/guidelines` is `^0.2`, and `post-update-cmd` runs `esoul-guidelines sync` after `boost:update`, so the generated guidelines never lag behind the installed package. `laravel/inertia-vue` now declares `package/laravel-inertia` next to `package/inertia-vue`, and `laravel/filament` declares `package/filament`; `laravel-lunar` still has no Lunar layer (see its own entry).

Migration: in `composer.json` raise the constraint to `^0.2` and add `"esoul-guidelines sync"` to `scripts.post-update-cmd` after `@php artisan boost:update --ansi || true`; add `package/laravel-inertia` (Inertia projects) and `package/filament` (Filament projects) to `esoul-guidelines.json`; run `composer update esoul-cz/guidelines` and commit the synced guidelines.

### 2026-11-23: The Laravel variants no longer add the Satis repository

Affects: `laravel-inertia`, `laravel-filament`, `laravel-inertia-filament`, `laravel-lunar` / layer `laravel/base` (`composer.json`).

The `repositories` entry for `https://packages.esoul.cz` is gone: `esoul-cz/laravel-cqrs`, `esoul-cz/cqrs` and `esoul-cz/guidelines` resolve from Packagist (the Satis repository only mirrored them). A project adds the entry back when it takes a private package.

Migration: remove the `repositories` entry from `composer.json` and run `composer update --lock`.
### 2026-11-23: New variant `astro-ssr`

Affects: new variant `astro-ssr` / layer `astro/ssr`, `variants/astro-ssr.json`.

A minimal Astro 7 SSR application on Node for archetype `B-admin` (`common` and `astro/ssr`, self-contained: it does not use `astro/static`
or `astro/cms-client`): `@astrojs/node` standalone, SQLite through `node:sqlite`, numbered migrations that refuse to start on a skipped id,
run-once seeds, admin sign-in with scrypt hashes, server-side sessions and a login throttle, the first admin created with
`pnpm admin:create` (no seeded credentials), Zod-validated settings, middleware with security headers and a body limit, `/healthz`,
a multi-stage `Dockerfile` (tini, uid 10001, healthcheck, graceful SIGTERM), `docker-compose.yml`, a `Jenkinsfile` composed from the
existing steps (a one-call `astroPipeline` is planned), Vitest and Playwright specs and `pnpm verify`. No React, Effect, enquiries or
image handling: a project adds those itself.

Migration: none, new variant.
### 2026-11-23: `astro-laravel-cms` is split into `laravel-cms`, `astro-cms-frontend` and `astro-cms`; new `astro-static`

Affects: variants `laravel-cms` (new), `astro-cms-frontend` (new), `astro-cms` (new, the thin root), `astro-static` (new); variant `astro-laravel-cms` is removed /
layers `astro/static`, `astro/cms-client`, `astro-cms/root`, `laravel/cms`, `variants/*.json`.

The monorepo with `frontend/` and `backend/` directories is replaced by three repositories, as the Astro guidelines describe (a monorepo with two git submodules, each
half built and deployed on its own): `laravel-cms` (the backend, a normal Laravel variant), `astro-cms-frontend` (the Astro site, own `Jenkinsfile`, guideline layers and a
`FROM scratch` image) and `astro-cms` (README, `docs/contract.md`, `docker/nginx.example` and `bin/add-submodules`, which runs `git submodule add` for the two
repositories). Each has its own `esoul-guidelines.json`. The contract check no longer needs `../backend`: the frontend keeps a copy of the golden export in `contract/`
and compares it with `../backend/tests/Fixtures/` only when that directory exists, otherwise only the schema version. `astro-static` is the same site without a CMS
(`deployStaticSite`, pages listed in `src/lib/site-pages.ts`); if it needs a form it starts its small backend from `laravel-cms`. The monorepo `Jenkinsfile`
(`astroCmsPipeline`) is gone: nothing is built from the root. The new Jenkinsfiles compose `fullNodeTest`, `buildStaticSite`, `dockerBuildMultiArch`, `deployKomodoStack`
and `deployStaticSite` and carry a marked comment for the planned `astroPipeline` / `staticSitePipeline`.

Migration: a project created from `astro-laravel-cms` keeps working as it is; to adopt the new shape, create `frontend` and `backend` as their own repositories
(history with `git subtree split --prefix=frontend` / `--prefix=backend`), apply the entries below to each, and replace the monorepo root with the layer `astro-cms/root`
(`bin/add-submodules`, `docs/contract.md`, the new `docker/nginx.example`; delete the root `Jenkinsfile`). Move `frontend/esoul-guidelines.json` content into each repository
(`framework/astro` for the frontend, `framework/laravel`, `package/filament`, `package/mutation-testing` for the backend) and run `esoul-guidelines sync` in each.

### 2026-11-23: Backend under a subdomain by default, path mode as an exception

Affects: `astro-cms` (and the monorepo of `astro-laravel-cms`) / layers `astro-cms/root` (`docker/nginx.example`, `README.md`), `laravel/cms` (`config/cms.php`, `CmsServiceProvider`,
`app/Infrastructure/Cms/AdminPathMode.php`, `append/.env.example`); `astro/cms-client` (`robots.txt`).

The NGINX example serves the site and the backend from one site definition with the backend on `admin.<domain>` (`APP_URL=https://admin.example.com`, host-only cookie,
never `.example.com`). The path mode (backend under `/admin` of the site's domain) is a commented exception: `SESSION_PATH=/admin` plus the new `CMS_ADMIN_PATH_MODE=true`,
which moves Livewire's update endpoint (it used to be `/livewire/update`, outside the cookie's path, so the panel could not work) to `/admin/livewire/update`. The backend
routes now include `/forms` and Livewire's `/livewire-<hash>/` prefix; `robots.txt` of the frontend disallows `/forms/` and `/livewire`.

Migration: a project on the same domain sets `SESSION_PATH=/admin` and `CMS_ADMIN_PATH_MODE=true` and adds `/forms` to the backend location of nginx; a project that can
move to a subdomain follows the new example (backend `.env`: `APP_URL`, `CMS_FORMS_ALLOWED_ORIGINS`; frontend: `cmsApiUrl` in the `Jenkinsfile`). Add the new
`cms.admin_path_mode` key, `AdminPathMode.php` and the provider hook from the layer.

### 2026-11-23: Contract v1 gets optional page fields `updatedAt`, `noindex`, `ogImage`

Affects: `laravel-cms`, `astro-cms-frontend`, `astro-cms` / layers `laravel/cms` (exporter, `Page`, pages migration, `PageResource`, `SampleContent`, golden export),
`astro/cms-client` (`schema.ts`, fixture, `contract/`), `astro-cms/root` (`docs/contract.md`).

Every exported page carries `updatedAt` (when the page itself was saved, ISO 8601), `noindex` and its own `ogImage` (the site image is the fallback). The frontend schema
accepts all three as optional, so an older backend still builds. The admin has a "Hide from search engines" switch and a social image upload per page.

Migration: add the columns `noindex` (boolean, default false) and `og_image` (string, nullable) to `pages` in a new migration, copy `Page.php`, the exporter, `PageResource.php`,
`SampleContent.php` and regenerate the golden export (`GOLDEN_UPDATE=1 php artisan test --filter=GoldenExportTest`); in the frontend copy `schema.ts`, run `pnpm contract:sync`.

### 2026-11-23: Sitemap `lastmod`, `llms.txt`, Markdown versions, SEO spec and a Vue island in the Astro templates

Affects: `astro-static`, `astro-cms-frontend` / layers `astro/static`, `astro/cms-client`.

- The sitemap carries `lastmod` from the page's `updatedAt` (CMS) or `src/lib/site-pages.ts` (static), never the build time; `noindex` pages stay out of the sitemap.
- `llms.txt` is generated from the same data as the pages (`src/pages/llms.txt.ts`, helper `src/lib/seo/llms.ts`); the CMS frontend also publishes Markdown versions
  (`/index.md`, `/<slug>.md`, advertised with `<link rel="alternate" type="text/markdown">`) and has one Vue island, the mobile menu (`@astrojs/vue`, `vue-tsc`, `eslint-plugin-vue`,
  Vitest). `BaseLayout` gets a `header` slot, a default Open Graph image (`public/og-default.png`, replace it) and the `markdownHref` prop.
- `pnpm verify` also checks `lastmod` on every sitemap entry, `llms.txt` and, with a CMS, the Markdown versions and the `noindex` flags of the export.
- Playwright gets the network guard (`e2e/fixtures.ts`: every request to another origin is aborted unless a spec routes it) and `e2e/seo.spec.ts` (title at most 60 and
  description 110-155 characters, one `h1`, canonical, Open Graph with image, `robots.txt`, `llms.txt`).

Migration: copy the changed files of the layers; list every indexable page in `src/lib/site-pages.ts` (static) and give pages descriptions of 110-155 characters; replace
`import { test } from '@playwright/test'` by `./fixtures` in the specs; with a CMS run `pnpm install` (new dependencies) and `pnpm contract:sync`.

### 2026-11-23: Astro scripts aligned, pnpm hardening, scratch image and Jenkinsfiles

Affects: `astro-static`, `astro-cms-frontend` / layers `astro/static`, `astro/cms-client`.

`lint` / `lint:fix` are now `eslint` / `eslint:fix`, `test` runs Vitest and `check` is `astro check` plus `vue-tsc --noEmit` with a CMS; `pnpm-workspace.yaml` gains `auditConfig.ignoreGhsas`
(empty; an entry needs a reason and a review date); `.prettierignore` skips the generated guideline files. `astro/cms-client` ships the multi-stage `Dockerfile` (`pnpm build`
and `pnpm verify` against the real export, final stage `FROM scratch` with `dist/` only) and `.dockerignore`. Both Jenkinsfiles run the `Checks` stage (lint, format, type check,
Vitest, audit, build and verify, Playwright) and then `deployStaticSite` (`astro-static`) or `dockerBuildMultiArch` + `deployKomodoStack` (CMS, with the Publish callback).
The image build takes the export token as a build argument (it exists only in the build stage) until `astroPipeline` can pass a BuildKit secret.

Migration: rename the scripts in `package.json`, `Jenkinsfile`, READMEs and any CI; add `auditConfig` to `pnpm-workspace.yaml`; a CMS site replaces its monorepo `deployStaticSite`
delivery by the image (`Dockerfile`, `.dockerignore`, `Jenkinsfile`); the host NGINX serves `/_astro/` from `current` (the deploy hook of the stack replaces `deployStaticSite`'s shared directory).

### 2026-11-23: Form endpoint in `laravel-cms`

Affects: `laravel-cms` (and the `backend/` of `astro-laravel-cms`) / layer `laravel/cms` (`config/cms.php`, `CmsServiceProvider`, `routes/forms.php`, `append/.env.example`).

`POST /forms/contact` (route `forms.contact`) takes the contact form of the static site: a public endpoint outside `/cms-api`, without the `web`
middleware (no CSRF token, session or cookie). It checks the `Origin` against `CMS_FORMS_ALLOWED_ORIGINS`, then runs the pipeline honeypot, rate limit per
client (address stored only as an HMAC), reCAPTCHA v3, delivery (a `form_submissions` row plus an e-mail to `CMS_FORMS_RECIPIENTS`) and a last-resort file
`storage/app/private/forms-last-resort/YYYY-MM.jsonl`. A plain post is answered with a 303 to `return_to` (`?form=sent` or `?form=<error code>`, fragment
`#form-result`), a JSON client gets `{status, code, message}`. The admin has a read-only "Form submissions" resource. See `docs/forms.md`.

Migration: copy the layer's new files (`app/Application/Contract/Cms/Form*.php`, `CaptchaVerifier.php`, `app/Domain/Cms/Form*.php`, `CaptchaVerdict.php`,
`SubmitFormCommand*.php`, the `Form*`/`Recaptcha*`/`ContactForm*`/`LastResortFile`/`CacheFormRateLimiter`/`ConfiguredFormRecipients`/`ClientAddressHasher`
classes, `FormSubmission` model and factory, the `FormSubmissions` Filament resource, `app/Infrastructure/Mail`, `resources/views/mail`, `routes/forms.php`,
`docs/forms.md`, the `Forms*` tests) and merge `CmsServiceProvider` and the `forms` array of `config/cms.php`. Run `php artisan migrate` (new table
`form_submissions`). Set `CMS_FORMS_ALLOWED_ORIGINS` (the site, e.g. `https://example.com`), `CMS_FORMS_RECIPIENTS`, `CMS_FORMS_RECAPTCHA_SECRET` (required in
production: the endpoint answers 503 without it) and `CMS_FORMS_RECAPTCHA_MIN_SCORE`; use `CACHE_STORE=file` or `database`. Route `/forms` to the backend in
nginx (next to `/cms-api` and the admin). The frontend posts the form to `<backend>/forms/contact`.

### 2026-11-23: The Laravel variants are published from `main`

Affects: the builder (`bin/templates publish`, `Jenkinsfile`); variants `laravel-inertia`, `laravel-filament`, `laravel-inertia-filament`,
`laravel-lunar` are published to `eSoul-cz/template-<variant>`. No template output changes.

After the builder checks, the `Jenkinsfile` of `main` builds each of these variants, runs their own checks (composer install, PHPStan,
code style, tests, pnpm audit, lint, typecheck, tests, build) and, only when all passed, runs `bin/templates publish <variant>`: one commit
whose tree is exactly the build, with the source commit in `.esoul/template.json` (nothing is pushed when nothing changed). The LSR and
Astro variants follow when their pipelines exist.

Migration: none for projects. Publishing uses the existing Jenkins GitHub App credential `github-app-esoul`; the owner installs the App
on the `template-*` repositories with Contents: write and creates the Packagist packages with their GitHub webhooks (README, "Publishing flow").

### 2026-11-23: The builder repository adopts the guidelines

Affects: the builder only (`esoul-guidelines.json`, `docs/project_guidelines/`, `AGENTS.md`, `CONTEXT.md`, `composer.json`).

This repository follows the guidelines itself with the layers `general` and `profile/internal`, and `composer update` runs
`esoul-guidelines sync` (`post-update-cmd`). No template output changes.

Migration: none.

### 2026-11-23: Guidelines 0.2

Affects: every variant / the builder (`composer.json`, `composer.lock`).

The builder requires `esoul-cz/guidelines` `^0.2` (it was `^0.1`, which for a `0.x` release can never resolve to 0.2) and renders the
guidelines of every variant with it: `dependencies.md` and `public-website.md` are new, the severity names changed. The layers of each
stack declare the guideline layers they need through their own `merge/esoul-guidelines.json`.

Migration: in the project raise the constraint to `^0.2`, run `composer update esoul-cz/guidelines`, then
`vendor/bin/esoul-guidelines sync` and commit the result. A project without Composer (a static Astro site) updates the global
installer instead (`composer global update esoul-cz/guidelines`).

### 2026-11-23: PR template and CONTEXT.md follow the workflow guideline

Affects: every variant / layer `common` (`.github/PULL_REQUEST_TEMPLATE.md`, `CONTEXT.md`).

The PR template gets a **Deploy notes** line (new or changed configuration, secrets, buckets, a migration that needs a backup first,
or `none`), `Bez testu:` becomes `No test:`, the mutation testing checkbox is gone (mutation testing has no gate, see the
`package/mutation-testing` guideline), and a new glossary term may land in a stacked PR. `CONTEXT.md` states that a commit message
starts with an uppercase letter (the example is `:sparkles: [checkout] Add delivery selection`) and gains the `Tracker` and
`Sources of truth` sections, both `TBD`.

Migration: optional. Copy the changed lines of `.github/PULL_REQUEST_TEMPLATE.md` into the project's template, and add the
`Tracker` and `Sources of truth` sections to `CONTEXT.md` (fill them in, or keep `TBD`).

## 2026-11-22: LSR requires PHP ^8.5

Affects: `lsr-inertia` / layer `lsr/base` (`composer.json`).

`require.php` is `^8.5` instead of `>=8.5`: every PHP 8 release from 8.5 on (8.6 included), but not an untested PHP 9.

Migration: optional; change `"php": ">=8.5"` to `"php": "^8.5"` in `composer.json` and run `composer update --lock`.

## 2026-11-22: LSR uses the shared coding standard

Affects: `lsr-inertia` / layer `lsr/base`; the builder (code quality only).

`.php-cs-fixer.php` of the LSR template builds its config with `ESoul\CodingStandard\Config::create()` from `esoul-cz/coding-standard`
(the same rules, now kept in one package) instead of a copied rules array; `friendsofphp/php-cs-fixer` comes in through that package.
The builder itself is now checked with PHPStan (level max) and the same coding standard; its output is unchanged.

Migration: `composer remove --dev friendsofphp/php-cs-fixer`, `composer require --dev esoul-cz/coding-standard:^0.1`, then in
`.php-cs-fixer.php` add `use ESoul\CodingStandard\Config;` and replace the rules with
`return Config::create($finder, __DIR__ . '/temp/tools/php-cs-fixer.cache');` (as the template's file does), and run `composer cs`.
Project-specific rule changes go into `->setRules([...Config::rules(), ...])`.

## 2026-11-22: Guidelines 0.1.1

Affects: all variants / the builder (`composer.lock`).

The builder renders the project guidelines with `esoul-cz/guidelines` 0.1.1. It describes pnpm without corepack and names the
settings pnpm 12 still reads from `.npmrc` (auth, registry, TLS, proxy, `local-address`).

Migration: `composer update esoul-cz/guidelines` and `vendor/bin/esoul-guidelines sync`.

## 2026-11-19: Packages move to the esoul-cz vendor on Packagist

Affects: all variants / layers `laravel/base`, `lsr/base`, `astro-cms/root`, `variants/*.json`, the builder.

Template packages are `esoul-cz/template-<variant>` and the guidelines installer is `esoul-cz/guidelines`, published on Packagist
(packages.esoul.cz mirrors them); the `esoul` vendor on Packagist belongs to another maintainer. The builder requires
`esoul-cz/guidelines` itself, so `bin/templates build` renders the guidelines without `--guidelines-bin`.

Migration: in `composer.json`, replace `esoul/guidelines` with `esoul-cz/guidelines` (`^0.1`) in `require-dev` and run
`composer update esoul-cz/guidelines`.

## 2026-11-18: Node 26 and the latest pnpm everywhere

Affects: all variants with a Node toolchain (`laravel-inertia`, `laravel-filament`, `laravel-inertia-filament`, `laravel-lunar`,
`lsr-inertia`, `astro-laravel-cms`) / layers `laravel/base`, `laravel/inertia-vue`, `astro/static`, `lsr/base`, `astro-cms/root` (Jenkinsfile).

Node 26 (`.nvmrc`, Dockerfiles, Jenkinsfiles) and the latest pnpm (12.21.0 at the time) replace the previous pins. Node 26 has no
corepack, so Docker Node stages install pnpm with `npm install --global pnpm@${PNPM_VERSION}`. pnpm 12 reads only auth, registry, TLS, proxy
and `local-address` settings from `.npmrc` and ignores `onlyBuiltDependencies`: build allow-lists are `allowBuilds`, and `ignoreScripts`,
`publicHoistPattern` and `minimumReleaseAge` live in `pnpm-workspace.yaml`.

Migration: update `.nvmrc`, `engines.node`, the `packageManager` field, the Dockerfile Node/pnpm stages and the `nodeImage` of the
Jenkinsfile; convert `onlyBuiltDependencies` to `allowBuilds` and move every other pnpm setting (not auth, registry, TLS, proxy or
`local-address`) from `.npmrc` to `pnpm-workspace.yaml`; run `pnpm install` and commit the lock file.

## 2026-11-18: The admin of the CMS can live on a subdomain

Affects: `astro-laravel-cms` / layer `astro-cms/root` (`docker/nginx.example`, `README.md`, `docs/contract.md`).

`docker/nginx.example` documents both placements: the admin on the same domain under `/admin` (the existing, still default layout) and
on `admin.<domain>` (a second server block that proxies `/admin`, `/livewire*`, `/cms-api`, `/up` to php-fpm and serves `/cms-media/`, `/_cms/`
and the panel assets; the Mode B blocks are complete, so uncommenting them gives a working configuration). The environment differences
(`APP_URL`, `SESSION_DOMAIN=null`, `cmsApiUrl`), why CORS is not needed (the static build fetches the export server-side) and the
`CMS_API_ALLOWED_IPS` requirement are written down; the root README has a "Where the admin lives" section on how to choose. No backend or
frontend code changed.

Migration: none for projects staying on one domain. To move the admin to a subdomain, take the Mode B blocks from `docker/nginx.example`, set
`APP_URL` to the admin host, point `cmsApiUrl` (Jenkinsfile) and `CMS_API_URL` at `https://admin.<domain>/cms-api/v1`, keep `SESSION_DOMAIN=null`
and add the DNS record.

## 2026-11-18: Template record by commit, licence handover and changelog

Affects: every variant / the builder (`bin/templates build`), `README.md`.

- `.esoul/template.json` is now `{"variant": ..., "commit": "<sha>"}` (the app-templates source commit the variant was built from; default
  `git rev-parse HEAD`, override `--commit=<sha>`). `--release=X.Y.Z` and the `version` key are gone; there are no template versions or tags.
- The root `composer.json` of every variant gets `post-create-project-cmd` steps (run before the layers' own ones such as `key:generate`) that move
  `LICENSE` to `.esoul/template-LICENSE` and set `license` to `proprietary` (also in `backend/composer.json` of `astro-laravel-cms`). The variant repository
  itself stays MIT. A variant entry's `into` directory may only contain letters, digits, `.`, `_`, `-` and `/`, because it is written unquoted
  into one of these steps.
- This file is the update interface for existing projects; variant repositories do not ship it, the update skill reads it from this repository.

Migration: projects created before this entry have no `commit` in `.esoul/template.json` (they may carry `version` or nothing). Set `commit` to the
first source commit whose entry you have applied, or let the update skill reconstruct it. Existing projects keep their current licence metadata;
change `license` to `proprietary` and move `LICENSE` to `.esoul/template-LICENSE` by hand if that is wanted.

## 2026-11-18: Guideline review: layering, tests, mail, mutation testing, ESLint

Affects: `laravel-inertia`, `laravel-filament`, `laravel-inertia-filament`, `laravel-lunar`, `astro-laravel-cms` / layers `laravel/base`,
`laravel/inertia-vue`, `laravel/filament`, `laravel/cms`; `lsr-inertia` / `lsr/base`; `astro-laravel-cms` / `astro/static`, `astro/cms-client`;
`common`.

- **Laravel layering** (`framework/laravel` guideline): Eloquent models (incl. `User`) in `App\Infrastructure\Models`, controllers and middleware in
  `App\Infrastructure\Http\*`, service providers in `App\Domain\Providers`, console commands in `App\Infrastructure\Console\Commands`;
  `esoul-cz/laravel-cqrs` is a runtime dependency with `config/cqrs.php` and `app/Domain/CQRS/{Command,Query}`. The layer rules are enforced by
  `tests/Unit/Architecture/LayerDependencyTest.php` (with `LayerRuleDetectionTest`). In `laravel/cms` the publish workflow is CQRS
  (`ExportContentQuery`, `GetPublishOverviewQuery`, `StartPublish`/`RefreshPublish`/`MarkPublishFailed`/`FinishPublish` commands) and
  `App\Application` only holds contracts and DTOs.
- **Rendered guidelines**: every variant ships `docs/project_guidelines/`, the guidelines block in `AGENTS.md` and `esoul-guidelines.lock`
  (rendered by the builder with `esoul-guidelines sync`); `esoul/guidelines` is a dev dependency. `common` adds `GLOSSARY.md`, `CONTEXT.md` keeps the
  conventions only, and the pull request template is in English.
- **Tests**: template tests follow the testing guidelines (`withoutVite()` in the base `TestCase`, parallel `composer test`, `phpunit.xml`
  strictness); `laravel/pao` is dropped.
- **Dev and quality**: Mailpit in the dev compose stack (`MAIL_MAILER=smtp`), `composer coverage`, `phpstan.neon` with `bleedingEdge`, Infection.
- **LSR**: health endpoint and local mutation testing.
- **Astro**: ESLint (flat config, `pnpm lint`) and a CSP smoke test (`smoke.spec.ts`).

Migration: layering changes touch most PHP files; follow `docs/project_guidelines/` and run the architecture tests, move files before adding new
code. Add `esoul-cz/laravel-cqrs`, `config/cqrs.php` and the CQRS directories, then run `vendor/bin/esoul-guidelines sync`. For the `astro-laravel-cms`
publish workflow, replace `PublishService` calls by the CQRS commands and queries. Remove `laravel/pao` from `composer.json`.

## 2026-11-18: First draft of the layered source

Affects: all variants (`laravel-inertia`, `laravel-filament`, `laravel-inertia-filament`, `laravel-lunar`, `lsr-inertia`, `astro-laravel-cms`) / all layers.

The layered source and the builder: layers `common`, `laravel/{skeleton,base,inertia-vue,filament,lunar,cms}`, `lsr/base`, `astro/{static,cms-client}`,
`astro-cms/root`; variants as listed in the README. The builder (`bin/templates build|list|skeleton:sync`) overlays `delete.txt`, `files/`, `merge/` (JSON) and
`append/` per layer, stamps the root `composer.json` (name, description, type, MIT) and renders the project guidelines. The `astro-laravel-cms` variant is
a monorepo (`frontend/` Astro 7 static site, `backend/` Laravel 13 + Filament 5 CMS with the Publish button that triggers a Jenkins build).

Migration: none (initial state).
