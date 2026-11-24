# eSoul app templates (source)

Layered source of the eSoul application templates. Instead of maintaining several near-identical template repositories by hand, every
shared piece (editor config, Laravel base, Inertia/Vue, Filament, ...) lives once as a **layer**. A **variant** is an ordered list of
layers; the builder overlays them into a ready-to-use project.

Future home: GitHub repo `eSoul-cz/app-templates`. Inside `esoul-infrastructure` it is a local repo at `templates/source/`.
The built variants are published as `eSoul-cz/template-<variant>` (Packagist `esoul-cz/template-<variant>`) and attached to the
infrastructure repo as submodules at `templates/<variant>/`.

| Variant | Layers | Published, checks (`kind`) |
| --- | --- | --- |
| `laravel-inertia` | common, laravel/skeleton, laravel/base, laravel/inertia-vue | yes, `laravel` |
| `laravel-filament` | common, laravel/skeleton, laravel/base, laravel/filament | yes, `laravel` |
| `laravel-inertia-filament` | common, laravel/skeleton, laravel/base, laravel/inertia-vue, laravel/filament | yes, `laravel` |
| `laravel-lunar` | common, laravel/skeleton, laravel/base, laravel/inertia-vue, laravel/lunar | yes, `laravel` |
| `lsr-inertia` | common, lsr/base | yes, `lsr` |
| `laravel-cms` | common, laravel/skeleton, laravel/base, laravel/filament, laravel/cms | yes, `laravel` |
| `astro-static` | common, astro/static | yes, `astro` |
| `astro-cms-frontend` | common, astro/static, astro/cms-client | yes, `astro` (fixture source) |
| `astro-cms` | common, astro-cms/root (thin monorepo root; the frontend and the `laravel-cms` backend are its git submodules) | yes, `root` |
| `astro-ssr` | common, astro/ssr | yes, `astro` |

## Choosing a variant

| The project is | Variant |
| --- | --- |
| A business web application with its own screens (Vue frontend) | `laravel-inertia` |
| An application whose admin is mostly data tables and forms (no custom frontend) | `laravel-filament` |
| Both: custom screens for users and a Filament admin | `laravel-inertia-filament` |
| An e-shop (Lunar owns the admin panel) | `laravel-lunar` |
| An application on the eSoul LSR packages (RoadRunner, Inertia and Vue) | `lsr-inertia` |
| A website without a CMS | `astro-static` |
| A website the client edits (headless CMS, the default for such a site) | `astro-cms` (root) + `astro-cms-frontend` + `laravel-cms` |
| A site that needs server code and a small admin of its own (an exception, needs an ADR) | `astro-ssr` |

Laravel is the default for a client application; take LSR when the product is built on the eSoul LSR packages (the existing LSR
products and internal tools). Astro static builds a plain website, Astro SSR a small application with an admin, and the CMS set (three
repositories: the root, the frontend and the backend) a website the client maintains. When none fits, say so in the first issue; the
lead decides.

## Usage

```bash
composer install
bin/templates list                          # variants and their layers
bin/templates build laravel-inertia         # → build/laravel-inertia/
bin/templates build --all --out=build       # every variant
bin/templates build laravel-inertia --commit=<sha>    # record another source commit in .esoul/template.json (default: HEAD of this repository)
bin/templates publish laravel-inertia --dry-run        # build and compare with template-laravel-inertia; `--repo=<url>` targets another repository (see "Publishing flow")
bin/templates skeleton:sync                 # refresh laravel/skeleton from upstream (default ^13)
vendor/bin/phpunit                          # builder tests (also: composer test)
composer phpstan                            # PHPStan, level max with the phpunit, strict and deprecation rules
composer cs                                 # PHP-CS-Fixer dry run with esoul-cz/coding-standard; `composer cs:fix` applies it
```

`build` empties `build/<variant>/` first, so output is reproducible and deterministic (sorted files, normalised JSON). All layers
of a variant are resolved before anything is deleted; an unknown layer or variant fails with a clear error and exit code 1.

CI is the `Jenkinsfile`: one call to `phpPackagePipeline` from the eSoul Jenkins libraries. It runs composer validate, PHPStan, the code
style check, composer audit, the builder tests and `bin/templates build --all` on PHP 8.5, then the tests on 8.4 and 8.6 after Composer
re-resolved the dependencies on that version. The builder supports PHP 8.4 and every later 8.x release (`^8.4`). On `main` the
`Jenkinsfile` then publishes the variants listed in it (see "Publishing flow").

## Layout

```
bin/templates            builder CLI (Symfony Console)
src/                     builder code (Catalog, Builder, JsonMerger, Glob, SkeletonSync, Publisher, commands)
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
subdirectory made of letters, digits, `.`, `_` and `-` (segments joined by `/`, no `..`), because the builder writes it unquoted into a
composer script; `exclude` globs are relative to the layer's `files/`, `merge/` and `append/` directories and skip matching files
(`delete.txt` is not affected).

After all layers, the builder **stamps the root `composer.json`** with the variant's `name` and `description` (the file is created
with `type: project` if no layer ships one, which is the case for the variants without PHP, e.g. `astro-static`).
It also writes `.esoul/template.json` (`{"variant": "...", "commit": "<sha>", "declined": []}`): `commit` is the app-templates source commit the variant was
built from, `--commit=<sha>` (7 to 40 hex characters) or, by default, `git rev-parse HEAD` of this repository. When this directory is not
the top level of a git checkout (and no `--commit` is given) nothing is recorded. `declined` is the project's own list, empty at creation
(see "Updating existing projects").

The stamp also **prepends two steps to `scripts.post-create-project-cmd`** (list-union with the layers' own steps, e.g. Laravel's
`key:generate`, which keep running after them), because a project created from a template is no longer the MIT template (see
"Licence of created projects"): `LICENSE` is moved to `.esoul/template-LICENSE` and `composer config license proprietary` runs. For each
`into` subdirectory that has its own `composer.json` (no variant uses `into` today) an extra
`@composer config --working-dir=<dir> license proprietary` step is added. Composer writes the value as `"license": ["proprietary"]`.

Finally it **renders the project guidelines**: it runs `esoul-guidelines sync --no-interaction` (the esoul-cz/guidelines installer,
a dependency of this repository: `vendor/bin/esoul-guidelines`, override with `--guidelines-bin`) in the variant root. Every variant therefore ships
`docs/project_guidelines/`, the guidelines block in `AGENTS.md` and `esoul-guidelines.lock` ready-made; a project created from it
updates them later with `composer update esoul-cz/guidelines` + `vendor/bin/esoul-guidelines sync` (below 1.0 only within the constraint's minor: a new minor needs it raised,
`composer require --dev esoul-cz/guidelines:^0.5 -W`). The Astro frontends and the SSR application
have no Composer dependency: their README ("Guidelines") documents `composer global`, the PHAR and the Docker image, and `astroPipeline` runs
`esoul-guidelines check` itself, with the version recorded in `esoul-guidelines.lock` (stage `Guidelines check`; no `Jenkinsfile` configuration). A missing installer, an unknown
guideline layer or a failing `sync` fails the build.

### The `common` layer

Repo-root files for every variant: `.editorconfig`, `.gitattributes` (Git LFS for `assets/**`), `.coderabbit.yaml`, `CONTEXT.md`
(conventions only: commit convention copied from `esoul-infrastructure/CONTEXT.md`, empty profile, tracker, tasking and sources-of-truth sections), `GLOSSARY.md` (domain vocabulary, filled in as the domain is modelled), minimal
`AGENTS.md` (the guidelines installer manages its block), `CLAUDE.md` (`@AGENTS.md`), `.github/PULL_REQUEST_TEMPLATE.md` (English, with
the `No test:` line and the Deploy notes line the workflow guideline asks for),
`renovate.json5` and `merge/esoul-guidelines.json` = `{"layers": ["general"]}` (the client profile; `profile/internal` is chosen
explicitly by an internal repository, never by a template). There is no `CODEOWNERS` and no `CONTRIBUTING` (the guidelines carry the
rules). Each stack layer adds its guideline layers through its own `merge/esoul-guidelines.json` (e.g. `framework/laravel`,
`package/laravel-inertia`, `package/filament`, `package/mutation-testing`), so what a variant declares is the union of its layers' lists.
Only existing guideline layers may be listed (`laravel-lunar` lists `package/filament` and `package/lunar`).

Every layer that ships a framework `composer.json` (the Laravel layers, `lsr/base`, the Astro CMS root) requires `esoul-cz/guidelines`
`^0.4` and adds `"esoul-guidelines sync"` to `scripts.post-update-cmd`, after Laravel Boost's `boost:update` where there is one, so
`composer update` re-renders `docs/project_guidelines/` (the installer README, "Sync on `composer update`"). Under 0.x `^0.4` stops before
0.5.0: the builder's own constraint and the layers' are raised together with each minor.

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

1. Add `variants/<name>.json` (`name` must equal the file name, `package` is `esoul-cz/template-<name>`, `description`, `layers`).
2. `bin/templates list` shows it; CI picks it up through `build --all` automatically.
3. To publish it: create the empty target repo `eSoul-cz/template-<name>`, register it on Packagist and add the repository webhook (see
   "Publishing flow"), and add the variant to `publishable` at the top of the `Jenkinsfile` with the `kind` of checks its own CI runs.

## Publishing flow

There are no template version tags: **the `main` HEAD of a variant repository is the latest template**.

1. A change to a layer is a PR against `eSoul-cz/app-templates`; the PR title follows the commit convention. Every change is
   recorded in [`CHANGELOG.md`](CHANGELOG.md) (see below).
2. A PR build runs the builder checks and `bin/templates build --all` (every variant must build); it runs no variant checks and
   publishes nothing.
3. On `main` the `Jenkinsfile` has a second part after the builder checks (stages `Publish: checkout`, `Publish: prepare`, `Check <variant>`,
   `Publish variants`): for each variant in `publishable` (the first lines of the `Jenkinsfile`: every variant) it builds the variant, runs
   the checks the variant's own CI runs, and only when **every** variant passed it runs `bin/templates publish <variant>` for each. The
   checks depend on the variant's `kind` (one function, `variantChecks`, in the `Jenkinsfile`; pnpm installs with `--no-frozen-lockfile`
   because a template has no lock file):

   | `kind` | Variants | Checks |
   | --- | --- | --- |
   | `laravel` | the `laravel-*` variants | PHP image: `composer install`, `.env` and app key, Wayfinder routes where the variant has them, PHPStan, code style, tests; Node image: `pnpm` install, audit and the JS checks the variant has (`full`: lint, format, typecheck, tests, build; `assets`: build) |
   | `lsr` | `lsr-inertia` | PHP image: `composer install`, `composer phpstan:ci`, `composer cs`, `composer test`; Node image: `pnpm` install, `eslint`, `format:check`, `tsc`, `test`, `build` |
   | `astro` | `astro-static`, `astro-cms-frontend`, `astro-ssr` | Node image: `pnpm` install, `eslint`, `format:check`, `check`, `test`, `build`, `verify`; `astro-cms-frontend` builds from the committed fixture (`CMS_SOURCE=fixture`) and also runs `pnpm run fixture:check` |
   | `root` | `astro-cms` | PHP image, nothing is built: `bin/add-submodules` parses and prints its usage, and `docs/contract.md` exists and is linked from the README |

   `publish` builds a pristine copy (the recorded `commit` is the commit being built) and **syncs it into `main` of
   `eSoul-cz/template-<variant>` as one commit** on top of the current tip: the commit's tree is exactly the build, its message is the
   subject of the source commit plus the line `Built from app-templates commit <sha>.`, and nothing is pushed when the tree equals the
   tip's. Nothing is tagged. The images are those of the variants' own pipelines (the PHP and Node testing images).

   `bin/templates publish <variant> --dry-run` builds and compares with the remote without pushing; `--repo`, `--branch`, `--message`
   and `--commit` override the defaults (`https://github.com/<package>.git`, `main`, the source commit's subject, `HEAD`). With
   `TEMPLATES_PUBLISH_TOKEN` set (a GitHub App installation token or a personal access token), git authenticates with it as
   `x-access-token` (a credential helper reads the variable; it is never part of a command line); without it git uses the machine's own
   authentication.

   The Jenkins credential is the existing `github-app-esoul` (type GitHub App, from the GitHub Branch Source plugin). Bound with
   `usernamePassword(credentialsId: 'github-app-esoul', usernameVariable: ..., passwordVariable: 'TEMPLATES_PUBLISH_TOKEN')`, its password
   is an installation token (valid for an hour), which the `Jenkinsfile` passes to the container by name. What the owner sets up once:
   the App is installed on the `template-*` repositories with `Contents: write`, and the credential's repository access covers them (a
   credential whose access is inferred from the job gets a token for `app-templates` only, see `runCommandInTest`); the empty
   `template-<variant>` repositories; and for each a Packagist package `esoul-cz/template-<variant>` with the GitHub webhook that tells
   Packagist about a push.
4. Packagist (vendor `esoul-cz`, packages `esoul-cz/template-<variant>`) is the primary distribution; it serves `dev-main` of each
   variant repository. `packages.esoul.cz` (Satis) is only a secondary mirror; no template lists it in `repositories`. Install:

   ```bash
   composer create-project esoul-cz/template-laravel-inertia:dev-main my-app
   laravel new my-app --using=esoul-cz/template-laravel-inertia     # Laravel variants; the installer passes --stability=dev
   ```

   Do not put a `version` key in the generated `composer.json`; Composer derives `dev-main` from the branch.

### Licence of created projects

The variant repositories are public and MIT (`LICENSE`, `"license": "MIT"`). A project created from one is the client's or eSoul's own
work, so the `post-create-project-cmd` steps stamped by the builder switch its root `composer.json` to `"license": "proprietary"` and keep
the template's MIT text only as attribution in `.esoul/template-LICENSE`. Nothing needs to be done by hand.

### The changelog

`CHANGELOG.md` is the update interface: newest entry first, each naming the **affected variants and layers** and carrying
**migration notes** for projects created earlier. Every change to `layers/`, `variants/` or the builder adds an entry; without one the
change is not complete.

Entries are written under the `## Unreleased` heading as `### YYYY-MM-DD: short title`, newest first. The date is stamped when the
change is merged: a commit made directly to `main` carries its commit date, a PR adds the date of the day and the merge corrects it
when it differs. The update skill orders entries by these dates (there are no tags, see "Updating existing projects"). The template
has no releases, so the section only grows; if it ever gets versions, a release turns `Unreleased` into the version heading, as the
guidelines say. Entries from before the section existed are `## YYYY-MM-DD: short title`.

## Updating existing projects

A project records where it came from in `.esoul/template.json`, written by every build:

```json
{
    "variant": "laravel-inertia",
    "commit": "<sha>",
    "declined": [{"title": "Adopt the new split shape", "reason": "The project keeps its own layout; decided in ADR 0007."}]
}
```

`variant` is the template the project follows and `commit` the last app-templates source commit it has caught up with. `declined` lists
what the project deliberately never takes: `title` is the changelog entry heading without its date, `reason` says why (one
sentence, a link to the decision when there is one). An item may also name a group of files that a project adopting the template
(`commit` unknown) declined: `{"title": "Renovate configuration", "reason": "...", "files": ["renovate.json5", ".github/"]}`; the update
skill skips those files. A fresh build has `"declined": []`; a record without the key means the same, and a
record with a `version` key or no `commit` is an old one that the update skill re-adopts.

Updating is done by the `esoul-project-update` skill of `eSoul-cz/skills`: it reads the entries of `CHANGELOG.md` in this
repository (`eSoul-cz/app-templates`) added after the recorded `commit` (variant repositories do not ship the changelog), pulls the latest
template, applies the entries that affect the project's variant and layers (skipping the `declined` ones, and offering to add an entry
to that list when the user declines it), follows their migration notes, and writes the new `commit` into `.esoul/template.json`. Changes
committed to a variant after the newest changelog entry are offered as an "unlisted changes" entry. Project customisations stay the
project's; the skill resolves conflicts with them, it does not overwrite them.

## Design notes

- Composer name, namespace and PHP constraint of the builder: `esoul-cz/app-templates`, `ESoul\AppTemplates`, PHP `^8.4` (dev runs 8.5).
  Symfony components are allowed `^7.3 || ^8.0`; PHPUnit `^12.0 || ^13.0`. `symfony/process` was added for `skeleton:sync`.
- The builder additionally stamps the root `composer.json` (`name`, `description`, `type`, `license: MIT`, the licence-handover
  `post-create-project-cmd` steps) and writes `.esoul/template.json` (`{variant, commit, declined}`). None of it is in the original contract; all of
  it is needed for `create-project` and the update flow. Variant repositories are public and MIT-licensed (`common` ships `LICENSE`);
  projects created from them become `proprietary` (decision D-23).
- The composer package names stamped from `variants/*.json` use the Packagist vendor `esoul-cz` (`esoul-cz/template-<variant>`).
- `skeleton:sync` option is `--constraint` (not `--version`, reserved by Symfony Console). It also strips files shipped by `common`
  and `laravel/base` (not only `laravel/base`), because `common` comes before the skeleton in the variant order and its
  `.editorconfig` / `.gitattributes` would otherwise be overwritten by the upstream ones.
- A layer directory is valid even when empty or without `layer.json`; only a missing directory is an "unknown layer". No schema
  validation of `layer.json`.
- Glob matching is a small own implementation (no braces, no negation). `delete.txt` globs apply to the target relative to `into`.
- Appended blank lines are collapsed, and `# comments` are deduplicated like any other line.
- JSON written by the builder is always re-encoded with 4-space indentation (also `package.json`); Prettier/Pint-style formatting in
  generated projects is not applied. Empty JSON objects survive as `{}`.
- Common layer texts (the PR template in English, as code-related text is, CodeRabbit filters excluding `.agents/**`, `.ai/**`, `.claude/**`, Renovate weekly
  schedule with dev-tooling grouping, `.gitattributes` diff drivers) are condensed from the reference projects; the PR template's
  Definition of Done assumes the guideline package ships the rules and the composer/pnpm script names of the Laravel base.
  `inheritance: true` in `.coderabbit.yaml` assumes the eSoul GitHub organisation has a central CodeRabbit policy.
- No versions: variants are consumed as `dev-main`, changes are tracked in one `CHANGELOG.md` for the whole source repo (decision D-12).
  A change is traceable to the source commit through `.esoul/template.json` of the built variant.
- Publishing: `bin/templates publish` builds a pristine copy of a variant and syncs it into its repository as one commit on top of the
  tip (history of the variant repository is the sequence of source commits that changed it); the `Jenkinsfile` runs it on `main` for the
  variants in its `publishable` list after their checks passed. Tag-less by design (D-12).
- Variants without PHP (the `astro-*` ones) still get the stamped root `composer.json` (name, MIT licence, the licence-handover
  `post-create-project-cmd` steps): it is what makes `composer create-project esoul-cz/template-<variant>` work.
- `skeleton:sync` formats the synced skeleton with Pint using `layers/laravel/base/files/pint.json` (Pint is installed into a temp dir),
  so untouched upstream files pass the base layer's `composer cs`; it skips the step when that file does not exist.
- Laravel layers (`laravel/base`, `inertia-vue`, `filament`, `lunar`):
  - `esoul-cz/guidelines` (`require-dev ^0.4`) comes from Packagist. It is not a Composer plugin: `post-update-cmd` runs `esoul-guidelines sync`.
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
  - `esoul-cz/laravel-cqrs ^1.0` is a runtime dependency of every Laravel template (from Packagist; no `repositories` entry);
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
  - pnpm is pinned to `12.21.0` (`packageManager`); pnpm 12 reads only auth, registry, TLS, proxy and `local-address` settings from
    `.npmrc`, so `ignoreScripts: true` and the Inertia `publicHoistPattern` live in `pnpm-workspace.yaml`. Node 26 ships without corepack:
    the Dockerfiles install pnpm with `npm install --global pnpm@${PNPM_VERSION}`.
  - Frontend packages follow the reference versions (Vite 8, Vitest 5, ESLint 10, TypeScript 6, Tailwind 4.3); `components.json` uses the
    `new-york` shadcn-vue style and the `@/design-system/ui` alias, but no shadcn component is added yet.
- `lsr/base`: assumptions, verification and open questions are in [`layers/lsr/base/README.md`](layers/lsr/base/README.md).
- Astro layers (`astro/static`, `astro/cms-client`, `astro/ssr`, `astro-cms/root`) and `laravel/cms`: a site with a CMS is three repositories, each created
  from its own template: `laravel-cms` (backend), `astro-cms-frontend` and the thin root `astro-cms` (documentation, the contract, the
  NGINX example and `bin/add-submodules`, which adds the other two as submodules). Each repository has its own `esoul-guidelines.json`,
  Jenkinsfile and pipeline (D-22 is reversed); the Jenkinsfiles are one call of `astroPipeline` (`astro-cms-frontend`: image mode with the
  CMS publish callback; `astro-static`: static mode; `astro-ssr`: image mode with `useVerifiedBuild: false`, its image builds from source).
  - The admin lives on the site's domain under `/admin` (default) or on `admin.<domain>`; `docker/nginx.example` holds both server layouts
    (Mode B commented) and the root README explains how to choose (decision D-17). No code differs: only nginx, `APP_URL`, and the
    `CMS_API_URL` and `cms.callbackUrl` of the frontend Jenkinsfile.
    `SESSION_DOMAIN` stays `null` in both, CORS is not needed because the static build fetches the export server-side.
  - `astro/cms-client` replaces `astro.config.mjs` of `astro/static` (it registers the media integration) and deletes `src/pages/index.astro`
    (the home page is rendered from the export by `[...slug].astro`). `scripts/verify-dist.mjs` loads an optional `scripts/verify-extra.mjs`.
  - Content model is minimal and generic: pages (slug, title, `isHome`, description, blocks `heading` / `text` / `image`) and one settings
    record; text is plain text, never HTML. Contract: `astro-cms/root/files/docs/contract.md`. No i18n, no shop; one Vue island (the mobile menu).
  - `CMS_SOURCE` is `api | fixture`; unset = `api` with a token, else `fixture`. There is no snapshot fallback. In CI (`CI` or `JENKINS_URL`)
    the fixture is used only with `CMS_SOURCE=fixture` set in the process environment, so a deploy build (CMS_SOURCE=api) fails when the API is unreachable.
  - The fixture is generated from the backend golden export, which the frontend carries as a copy in `contract/` (`pnpm contract:sync` copies it
    from `../backend/tests/Fixtures`; `pnpm fixture:check` always compares the schema version and, when `../backend` exists, the copy with the
    backend originals). A builder test (`ContractCopyTest`) guards the copy against the `laravel/cms` golden export.
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
  - `pnpm-workspace.yaml` has `minimumReleaseAge: 1440` and `allowBuilds` (pnpm 12 ignores `onlyBuiltDependencies`).
  - ESLint (`eslint.config.js`, flat config: ESLint recommended + typescript-eslint + eslint-plugin-astro, `no-explicit-any` as error) ships in `astro/static`
    with `pnpm eslint` / `pnpm eslint:fix`; the Vue integration (`@astrojs/vue`, `vue-tsc`) arrives with `astro/cms-client`.
    The default CI check set is `pnpm audit`, `eslint`, `format:check`, `check`, `build`, `verify`, `test:e2e`. The CSP is Astro's `security.csp` (a `<meta>` tag with hashes);
    `frame-ancestors` / `X-Frame-Options` and the other response headers stay in nginx. `smoke.spec.ts` asserts the CSP tag and a console free of errors and violations.
  - Prettier in the frontend follows the baseline (printWidth 150, tabWidth 4); `esoul-guidelines.json` is in `.prettierignore` (written by the builder / installer).
  - Guidelines: `framework/astro` for every Astro template, `framework/laravel` and its packages for `laravel-cms`; since each repository has its own
    config, topics no longer merge across the halves of a site.
