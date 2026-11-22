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

### 2026-11-23: PR template and CONTEXT.md follow the workflow guideline

Affects: every variant / layer `common` (`.github/PULL_REQUEST_TEMPLATE.md`, `CONTEXT.md`).

The PR template gets a **Deploy notes** line (new or changed configuration, secrets, buckets, a migration that needs a backup first,
or `none`), `Bez testu:` becomes `No test:`, the mutation testing checkbox is gone (mutation testing has no gate, see the
`package/mutation-testing` guideline), and a new glossary term may land in a stacked PR. `CONTEXT.md` states that a commit message
starts with an uppercase letter (the example is `:sparkles: [checkout] Add delivery selection`) and gains the `Tracker` and
`Sources of truth` sections, both `TBD`.

Migration: optional. Copy the changed lines of `.github/PULL_REQUEST_TEMPLATE.md` into the project's template, and add the
`Tracker` and `Sources of truth` sections to `CONTEXT.md` (fill them in, or keep `TBD`).

### 2026-11-23: New variant `laravel-cms`

Affects: new variant `laravel-cms` / layers `laravel/cms`, `variants/laravel-cms.json`.

The Laravel + Filament headless CMS that used to live in `backend/` of `astro-laravel-cms` is its own variant (`common`, `laravel/skeleton`,
`laravel/base`, `laravel/filament`, `laravel/cms`): a normal Laravel repository with its own `Jenkinsfile`, guidelines and image. It is also
the small Laravel backend that a static site without a CMS uses for forms.

Migration: none for existing Laravel projects. A project created from `astro-laravel-cms` keeps its `backend/` as it is (see the entry that retires that variant).

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
