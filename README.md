# eSoul app templates (source)

Layered source of the eSoul application templates. Instead of maintaining six near-identical template repositories by hand, every
shared piece (editor config, Laravel base, Inertia/Vue, Filament, ...) lives once as a **layer**. A **variant** is an ordered list of
layers; the builder overlays them into a ready-to-use project.

Future home: GitHub repo `eSoul-cz/app-templates`. Inside `esoul-infrastructure` it is a local repo at `templates/source/`.
The built variants are published as `eSoul-cz/template-<variant>` (Composer `esoul/template-<variant>`) and attached to the
infrastructure repo as submodules at `templates/<variant>/`.

| Variant | Layers |
| --- | --- |
| `laravel-inertia` | common, laravel/skeleton, laravel/base, laravel/inertia-vue |
| `laravel-filament` | common, laravel/skeleton, laravel/base, laravel/filament |
| `laravel-inertia-filament` | common, laravel/skeleton, laravel/base, laravel/inertia-vue, laravel/filament |
| `laravel-lunar` | common, laravel/skeleton, laravel/base, laravel/inertia-vue, laravel/lunar |
| `lsr-inertia` | common, lsr/base |
| `astro-laravel-cms` | common, astro-cms/root, astro/static + astro/cms-client (`frontend/`), laravel/skeleton + laravel/base + laravel/filament + laravel/cms (`backend/`) |

## Usage

```bash
composer install
# Until esoul/guidelines is published on packages.esoul.cz, point builds at the sibling checkout:
#   bin/templates build <variant> --guidelines-bin=../../guidelines/bin/esoul-guidelines
bin/templates list                          # variants and their layers
bin/templates build laravel-inertia         # → build/laravel-inertia/
bin/templates build --all --out=build       # every variant
bin/templates build laravel-inertia --release=1.4.0   # also writes .esoul/template.json
bin/templates skeleton:sync                 # refresh laravel/skeleton from upstream (default ^13)
vendor/bin/phpunit                          # builder tests
```

`build` empties `build/<variant>/` first, so output is reproducible and deterministic (sorted files, normalised JSON). All layers
of a variant are resolved before anything is deleted; an unknown layer or variant fails with a clear error and exit code 1.

## Layout

```
bin/templates            builder CLI (Symfony Console)
src/                     builder code (Catalog, Builder, JsonMerger, Glob, SkeletonSync, commands)
tests/                   PHPUnit tests (fixture layers are generated in a temp dir)
layers/<group>/<name>/   a layer, or layers/<name>/ for top-level ones (e.g. `common`)
  layer.json             {"description": "..."}
  files/**               copied into the target, later layers overwrite
  merge/**/*.json        deep-merged into the same relative path
  append/**              lines appended to the same relative path, skipping lines already present
  delete.txt             globs removed from the target before this layer's files are applied
variants/<variant>.json  ordered layer list
build/                   output (gitignored)
```

## Contract

Per layer, in this order, relative to the entry's `into` directory:

1. **`delete.txt`**: one glob per line (`#` comments and blank lines ignored), matched against the paths already in the target
   (files and directories; a matched directory is removed recursively). Globs: `*`/`?` stay within a path segment, `**` crosses
   segments, `**/` also matches zero directories, dotfiles match; no brace expansion.
2. **`files/**`**: copied, overwriting earlier layers (executable bit is preserved).
3. **`merge/**/*.json`**: merged into the same relative path (created when missing); `merge/esoul-guidelines.json` always merges
   into the variant root, whatever the entry's `into`, so a monorepo has one guideline config. Objects merge recursively, later wins for
   scalars, a `null` value removes the key (`laravel/base` drops the skeleton's `laravel/pao` this way), lists of scalars are
   unioned keeping first-seen order, other lists are replaced. Output is pretty-printed with 4
   spaces, unescaped slashes and a trailing newline. Non-JSON files in `merge/` are an error.
4. **`append/**`**: text lines appended to the same relative path (created when missing). A non-blank line already present in
   the file is skipped; blank lines are kept for spacing but never stacked.

Variant entry: `{"layer": "laravel/base", "into": "backend", "exclude": ["Jenkinsfile", ".github/**"]}`. `into` is a relative
subdirectory; `exclude` globs are relative to the layer's `files/`, `merge/` and `append/` directories and skip matching files
(`delete.txt` is not affected).

After all layers, the builder **stamps the root `composer.json`** with the variant's `name` and `description` (the file is created
with `type: project` if no layer ships one, which is the case for the `astro-laravel-cms` monorepo unless `astro-cms/root` adds it).
With `--release=X.Y.Z` it writes `.esoul/template.json` (`{"variant": "...", "version": "X.Y.Z"}`).

Finally it **renders the project guidelines**: it runs `esoul-guidelines sync --no-interaction` (the esoul/guidelines installer,
default `vendor/bin/esoul-guidelines`, override with `--guidelines-bin`) in the variant root. Every variant therefore ships
`docs/project_guidelines/`, the guidelines block in `AGENTS.md` and `esoul-guidelines.lock` ready-made; a project created from it
updates them later with `composer update esoul/guidelines` + `vendor/bin/esoul-guidelines sync`. A missing installer, an unknown
guideline layer or a failing `sync` fails the build.

### The `common` layer

Repo-root files for every variant: `.editorconfig`, `.gitattributes` (Git LFS for `assets/**`), `.coderabbit.yaml`, `CONTEXT.md`
(conventions only: commit convention copied from `esoul-infrastructure/CONTEXT.md`, empty project profile and tasking sections), `GLOSSARY.md` (domain vocabulary, filled in as the domain is modelled), minimal
`AGENTS.md` (the guidelines installer manages its block), `CLAUDE.md` (`@AGENTS.md`), `.github/PULL_REQUEST_TEMPLATE.md`,
`renovate.json5` and `merge/esoul-guidelines.json` = `{"layers": ["general"]}`. Each stack layer adds its guideline layers
through its own `merge/esoul-guidelines.json` (e.g. `framework/laravel`, `package/inertia-vue`). Only existing guideline layers
may be listed: `laravel/filament` and `laravel/lunar` add `package/filament` / `package/lunar` once those are written (plan G3).

### The `laravel/skeleton` layer

`files/` is a verbatim copy of upstream `laravel/laravel`; `layer.json` records the resolved version under `upstream`. Do not edit
it by hand: run `bin/templates skeleton:sync [--constraint=^13]` (it runs `composer create-project laravel/laravel ... --no-install
--no-scripts --prefer-dist` in a temp dir). The sync strips files that `common` or `laravel/base` ship in their own `files/`, since
those overwrite them anyway; **re-run the sync after those layers gain or lose files**. Everything else the Laravel layers change
about `composer.json`, `package.json`, `.gitignore`, `.env.example`, ... goes through `merge/` and `append/`.

> `--constraint` (not `--version`) because `--version` is reserved by Symfony Console.

## Adding a layer

1. Create `layers/<group>/<name>/` with a `layer.json` description.
2. Put whole files under `files/`; use `merge/` for JSON files other layers also touch (`composer.json`, `package.json`,
   `esoul-guidelines.json`), `append/` for line files (`.gitignore`, `.env.example`), `delete.txt` to remove upstream files.
3. Keep layers additive and independent: a layer must work in every variant that lists it, so do not assume a later layer exists.
4. Reference it from the variants that need it, in dependency order (later layers win).
5. `bin/templates build <variant>`, then run the variant's own checks (install, lint, tests) in `build/<variant>/`.

## Adding a variant

1. Add `variants/<name>.json` (`name` must equal the file name, `package` is `esoul/template-<name>`, `description`, `layers`).
2. `bin/templates list` shows it; CI picks it up through `build --all` automatically.
3. Create the empty target repo `eSoul-cz/template-<name>` and register it in Satis (`satis.json` in `esoul-packages`).

## Publishing flow

1. A change to a layer is a PR against `eSoul-cz/app-templates`; the PR title follows the commit convention.
2. CI on the PR builds every variant (`bin/templates build --all`) and runs each variant's checks (composer install, Pint, Larastan,
   PHPUnit, pnpm lint/test/build as applicable).
3. On merge to `main` CI builds every variant again with `--release=X.Y.Z` and **force-syncs `build/<variant>` into
   `eSoul-cz/template-<variant>` as one commit** whose message is the source PR title, then tags it `vX.Y.Z`. The version is one
   semver line for the whole source repo, so every variant repo gets the same tag (a variant whose built content is unchanged
   except for `.esoul/template.json` is still tagged, which keeps the version numbers aligned).
4. Satis at `packages.esoul.cz` picks up the tags (add the `template-*` repos to `satis.json`, rebuilt by the Jenkins job), making
   them installable:

   ```bash
   composer create-project esoul/template-laravel-inertia my-app --repository=https://packages.esoul.cz
   ```

   Do not put a `version` key in the generated `composer.json`; Composer derives it from the tag.

## Updating existing projects

A project records where it came from in `.esoul/template.json` (`{"variant": "laravel-inertia", "version": "1.4.0"}`; written by the
build when `--release` is passed, so every tagged template already contains it). To update:

```bash
git remote add template git@github.com:eSoul-cz/template-laravel-inertia.git   # once
git fetch template --tags
git diff v1.4.0 v1.5.0 | git apply --3way            # the template's own change between the two versions
```

Review the conflicts, bump `version` in `.esoul/template.json`, and open a PR. Because the diff is taken between two template tags
(not against the project), only template changes are applied and project customisations show up as conflicts to resolve by hand.
For the `astro-laravel-cms` variant the same works unchanged (`frontend/` and `backend/` paths are part of the diff).

## Draft assumptions

- Composer name, namespace and PHP constraint of the builder: `esoul/app-templates`, `ESoul\AppTemplates`, PHP `^8.4` (dev runs 8.5).
  Symfony components are allowed `^7.3 || ^8.0`; PHPUnit `^12.0 || ^13.0`. `symfony/process` was added for `skeleton:sync`.
- The builder additionally stamps the root `composer.json` (`name`, `description`, `type`, `license: MIT`) and optionally writes
  `.esoul/template.json` (`--release`). Neither is in the original contract; both are needed for `create-project` and the update flow.
  Variant repositories are public and MIT-licensed (`common` ships `LICENSE`); whether a project created from a template keeps
  the MIT metadata is an open decision.
- `skeleton:sync` option is `--constraint` (not `--version`, reserved by Symfony Console). It also strips files shipped by `common`
  and `laravel/base` (not only `laravel/base`), because `common` comes before the skeleton in the variant order and its
  `.editorconfig` / `.gitattributes` would otherwise be overwritten by the upstream ones.
- A layer directory is valid even when empty or without `layer.json`; only a missing directory is an "unknown layer". No schema
  validation of `layer.json`.
- Glob matching is a small own implementation (no braces, no negation). `delete.txt` globs apply to the target relative to `into`.
- Appended blank lines are collapsed, and `# comments` are deduplicated like any other line.
- JSON written by the builder is always re-encoded with 4-space indentation (also `package.json`); Prettier/Pint-style formatting in
  generated projects is not applied. Empty JSON objects survive as `{}`.
- Common layer texts (PR template in Czech, CodeRabbit filters excluding `.agents/**`, `.ai/**`, `.claude/**`, Renovate weekly
  schedule with dev-tooling grouping, `.gitattributes` diff drivers) are condensed from the reference projects; the PR template's
  Definition of Done assumes the guideline package ships the rules and the composer/pnpm script names of the Laravel base.
  `inheritance: true` in `.coderabbit.yaml` assumes the eSoul GitHub organisation has a central CodeRabbit policy.
- Versioning: one semver line for the whole source repo, all variants tagged together; no per-variant changelog yet.
- Publishing and CI are described, not implemented: no Jenkinsfile or GitHub workflow exists in this repo yet.
- `astro-laravel-cms` has no root `composer.json` from the builder's point of view except the stamped stub; if the monorepo should
  not be installable through Satis, drop the stamping for variants without a root composer file.
- `skeleton:sync` formats the synced skeleton with Pint using `layers/laravel/base/files/pint.json` (Pint is installed into a temp dir),
  so untouched upstream files pass the base layer's `composer cs`; it skips the step when that file does not exist.
- Laravel layers (`laravel/base`, `inertia-vue`, `filament`, `lunar`):
  - `esoul/guidelines` (`require-dev ^0.1`, matching its first tag) is not published at `packages.esoul.cz` yet, so `composer install`
    of a built variant fails until it is; verification removed it from the built `composer.json`. It is not a Composer plugin.
  - This repository does not `require` `esoul/guidelines` yet (it is unpublished, so `composer install` here would fail); builds pass
    `--guidelines-bin` until it is, then the dependency is added and the default `vendor/bin/esoul-guidelines` applies. Locks in built
    variants record `package_version: dev` while the installer runs from a checkout.
  - `bootstrap/providers.php` is an explicit list owned by the LAST layer of a variant that touches it (base: `AppServiceProvider`;
    filament and `laravel/cms` overwrite it). No provider auto-discovery.
  - Layout follows the `framework/laravel` guideline: Eloquent models (including `User`) in `App\Infrastructure\Models`, controllers in
    `App\Infrastructure\Http\Controllers`, middleware in `App\Infrastructure\Http\Middleware`, service providers (including the Filament panel
    provider) in `App\Domain\Providers`. The skeleton's `app/Models`, `app/Http` and `app/Providers` are removed through `delete.txt`;
    `laravel/base` overrides `config/auth.php`, `UserFactory` and `DatabaseSeeder` (and the skeleton copies of these files, and of `.env.example`,
    were stripped as `skeleton:sync` does) and the model names its factory with `#[UseFactory]`. Console commands are registered from
    `app/Infrastructure/Console/Commands` (`bootstrap/app.php`, owned by `laravel/base` and `laravel/inertia-vue`).
  - `App\Domain\Providers` is the one namespace of the domain that may use the container, facades and the framework.
    `tests/Unit/Architecture/LayerDependencyTest.php` (helpers in `tests/Support/Architecture`, plain PHPUnit, no app boot) reads every file in
    `app/` with the tokenizer: `App\Domain` outside `CQRS` and `Providers` must not use `Illuminate\Database`/`Http`/`Support\Facades`, the container,
    the session, `App\Application` or `App\Infrastructure`; `App\Domain\CQRS` may use `App\Infrastructure\Models` only; `App\Application` must not
    use `App\Infrastructure`. `LayerRuleDetectionTest` feeds each rule in-memory samples (including the Providers exemption).
  - `esoul-cz/laravel-cqrs ^1.0` is a runtime dependency of every Laravel template (from `packages.esoul.cz`, already in `repositories`);
    `config/cqrs.php` is the published config of the reference projects (discovery of `app/Domain/CQRS`), and `app/Domain/CQRS/{Command,Query}`
    exist (`.gitkeep`) because the buses require them.
  - Local dev mail: the dev `docker-compose.yml` has a Mailpit service (inbox on `MAILPIT_UI_PORT`, default 8025, SMTP published on `MAIL_PORT`);
    `.env.example` uses `MAIL_MAILER=smtp`, `127.0.0.1:1025` and the `laravel` service overrides `MAIL_HOST=mailpit`. `laravel/base` ships its
    own `.env.example` (the skeleton's with the mail block changed) because `append/` cannot change an existing line.
  - `composer coverage` (alias of `test:coverage`) writes `test-coverage/` (gitignored); `phpstan.neon` includes `bleedingEdge`.
  - Composer `scripts.test` is `artisan test --parallel` (a string, because the builder unions script lists and would keep the skeleton's
    `artisan test`); Pint/Larastan/Infection/PHPUnit versions come from the reference projects (`larastan ^3.0`, `infection ^0.35`, `phpunit ^13.2`).
  - `laravel/boost` is a dev dependency; `boost:update` runs after `composer update` but may fail without a `.env` (`|| true`).
  - Filament admin: `users.is_admin` boolean column (migration `0001_01_01_000003`), not mass assignable; `php artisan admin:promote <email>` is a
    small addition so the first admin can be created. Panel id/path `admin`, colour Amber (Filament default).
  - Lunar admin is moved to `/admin` (Lunar default is `/lunar`); Lunar config is not published; `lunar:install` is a manual step after `migrate`.
  - `laravel-filament` (no Inertia) keeps the skeleton's Vite/Tailwind `app.js`; its Jenkinsfile (from base) runs only the asset build as the JS check.
    Inertia variants overwrite the Jenkinsfile with the all-defaults `laravelPipeline` call. Both use the placeholder image `replace-with-app-name` and
    no `stack` (nothing is deployed until the project sets one).
  - Inertia SSR is off (`INERTIA_SSR_ENABLED=false` in `.env.example` and `phpunit.xml`); there is no `ssr.ts`. Tests call `withoutVite()` (TestCase
    override) because PHP tests run before the asset build. Root view uses the `@inertia` / `@inertiaHead` directives (Inertia 3.7).
  - Production image: Supervisor runs php-fpm, queue worker and scheduler as in vono-iis (no `queue:work <connection>` argument, no seeders); the Node
    stage does not need PHP (`WAYFINDER_TYPES_PREGENERATED=true`, generated files come from the PHP stage); `pnpm install --frozen-lockfile`
    requires a committed `pnpm-lock.yaml`. The image was built and smoke-tested (supervisor + php-fpm RUNNING, healthcheck OK) for `laravel-inertia`
    only, from a build dir that already had lock files; the dev compose stack was not started.
  - Dev compose runs MariaDB `12.3` and overrides `DB_*` for the `laravel` service only; the dev image runs php-fpm, queue, scheduler and Vite as the
    host user (`WWWUSER`/`WWWGROUP`). Vite is reached directly on `VITE_PORT` (no nginx proxy).
  - pnpm is pinned to `10.20.0` (`packageManager`); the skeleton's `.npmrc` (`ignore-scripts=true`) is kept, so no `pnpm-workspace.yaml` allow-list.
  - Frontend packages follow the reference versions (Vite 8, Vitest 5, ESLint 10, TypeScript 6, Tailwind 4.3); `components.json` uses the
    `new-york` shadcn-vue style and the `@/design-system/ui` alias, but no shadcn component is added yet.
- `lsr/base`: assumptions, verification and open questions are in [`layers/lsr/base/README.md`](layers/lsr/base/README.md).
- `astro-laravel-cms` layers (`astro/static`, `astro/cms-client`, `laravel/cms`, `astro-cms/root`):
  - `astro/cms-client` replaces `astro.config.mjs` of `astro/static` (it registers the media integration) and deletes `src/pages/index.astro`
    (the home page is rendered from the export by `[...slug].astro`). `scripts/verify-dist.mjs` loads an optional `scripts/verify-extra.mjs`.
  - Content model is minimal and generic: pages (slug, title, `isHome`, description, blocks `heading` / `text` / `image`) and one settings
    record; text is plain text, never HTML. Contract: `astro-cms/root/files/docs/contract.md`. No i18n, no Vue islands, no shop.
  - `CMS_SOURCE` is `api | fixture`; unset = `api` with a token, else `fixture`. There is no snapshot fallback. In CI (`CI` or `JENKINS_URL`)
    the fixture is used only with `CMS_SOURCE=fixture` set in the process environment, so a deploy build (CMS_SOURCE=api) fails when the API is unreachable.
  - The fixture is generated from the backend golden export (`pnpm fixture:build`), the golden test shares `pixel.png` with it; `pnpm fixture:check` compares them.
    Frontend and backend are therefore assumed to live in one monorepo (`../backend/tests/Fixtures`).
  - Source Sans 3 (SIL OFL, copied from a reference project's `src/assets/fonts`) is the placeholder local font.
  - Provider list: `laravel/cms` overwrites `bootstrap/providers.php` with `[AppServiceProvider, AdminPanelProvider, CmsServiceProvider]` (the last layer owns the list).
    The `cms-media` / `cms-public` disks are added by `CmsServiceProvider` (config/filesystems.php stays the skeleton's).
  - The CMS follows the layer rules: `App\Application` holds only contracts (`Contract\Cms\PublicMediaStore`, `BuildTrigger`, implemented by
    `Infrastructure\Cms\PublicMedia` / `JenkinsClient` and bound in `CmsServiceProvider`), DTOs and `ContentChanges` (a cache stamp). The export
    and the publish workflow are CQRS: `ExportContentQuery`, `GetPublishOverviewQuery`, and the commands `StartPublish`, `RefreshPublish`,
    `MarkPublishFailed`, `FinishPublish` under `App\Domain\CQRS`, called through the `QueryBus`/`CommandBus` facades by the controllers and the
    Filament page. Controllers, middleware and the prune command moved to `Infrastructure\Http\{Controllers,Middleware}` and
    `Infrastructure\Console\Commands`.
  - Admin uploads sit on a public-visibility `cms-media` disk (served at `/cms-media/` by nginx, images only, CSP sandbox) so Filament can preview them.
  - `pnpm-workspace.yaml` has `minimumReleaseAge: 1440`, and both `onlyBuiltDependencies` and `allowBuilds` (pnpm 10.20.0 reads only the former); `trustPolicy: no-downgrade` is left out because pnpm 10.20.0 (`packageManager`) predates it.
  - ESLint (`eslint.config.js`, flat config: ESLint recommended + typescript-eslint + eslint-plugin-astro, `no-explicit-any` as error) ships in `astro/static`
    with `pnpm lint` / `pnpm lint:fix`; there are no Vue rules because the layers have no `.vue` files (a project with Vue islands adds `eslint-plugin-vue` and `vue-tsc`).
    The default CI check set is `pnpm audit`, `lint`, `format:check`, `check`, `build`, `verify`, `test:e2e`. The CSP is Astro's `security.csp` (a `<meta>` tag with hashes);
    `frame-ancestors` / `X-Frame-Options` and the other response headers stay in nginx. `smoke.spec.ts` asserts the CSP tag and a console free of errors and violations.
  - Prettier in `frontend/` follows the baseline (printWidth 150, tabWidth 4); `esoul-guidelines.json` is in `.prettierignore` (written by the builder / installer).
  - `astroCmsPipeline` is planned only; the root `Jenkinsfile` documents the intended configuration with example names (`example-project`).
  - The monorepo has ONE guideline config at its root (`general`, `framework/astro`, `framework/laravel`, `package/mutation-testing`), so
    same-named topics merge across frontend and backend (e.g. one `frontend.md` with Astro and Laravel sections). Per-directory guideline
    sets would need installer support; open question. The root `composer.json` requires `esoul/guidelines` for `sync`/`check` there.
