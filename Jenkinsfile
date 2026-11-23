@Library(['testing', 'dockerHelpers', 'pipelines']) _

// The variants that are published to eSoul-cz/template-<variant> from `main`, in one place. `kind` says which checks the variant's own
// CI runs (defined once in `variantChecks` below):
//   laravel  composer install, artisan setup, PHPStan, code style, tests and the JS checks; `wayfinder` says whether the variant has
//            Laravel Wayfinder (routes are generated before the JS build) and `js` which JS checks its own CI runs: `full` (audit,
//            eslint, prettier, typecheck, tests, build) or `assets` (audit and the asset build only).
//   lsr      the same for an LSR application (Composer and pnpm, no Laravel): PHPStan, code style, tests, then the JS checks.
//   astro    pnpm only: eslint, prettier, `astro check`, tests, build and `pnpm verify`. `env` is added to the check container and
//            `extra` is a list of further commands (the contract check of the CMS frontend).
//   root     no build: the monorepo root of `astro-cms` ships a script and the contract document, so those are checked.
def publishable = [
    'laravel-inertia'         : [kind: 'laravel', wayfinder: true, js: 'full'],
    'laravel-filament'        : [kind: 'laravel', wayfinder: false, js: 'assets'],
    'laravel-inertia-filament': [kind: 'laravel', wayfinder: true, js: 'full'],
    'laravel-lunar'           : [kind: 'laravel', wayfinder: true, js: 'full'],
    'laravel-cms'             : [kind: 'laravel', wayfinder: false, js: 'assets'],
    'lsr-inertia'             : [kind: 'lsr'],
    'astro-static'            : [kind: 'astro'],
    // Builds from the committed fixture (the pipeline's `checkEnv`), never from a backend.
    'astro-cms-frontend'      : [kind: 'astro', env: [CMS_SOURCE: 'fixture'], extra: ['pnpm run fixture:check']],
    'astro-ssr'               : [kind: 'astro'],
    'astro-cms'               : [kind: 'root'],
]

// The builder only: composer validation, PHPStan, code style, audit and tests, and `build --all` on every pull request.
// A fixed --commit keeps that build independent of git inside the test container.
// These checks may run on the Quick node (label quick, reserved for the quick jobs of the infrastructure repositories)
// or on a general one, whichever has a free executor, and have to end within the 15 minutes that node allows.
// Publishing below asks for a node of its own and keeps its own, longer time limit.
phpPackagePipeline(
    additionalTests: [[name: 'Build all variants', command: 'php bin/templates build --all --commit=0000000']],
    matrix: [
        [name: 'PHP 8.4', image: 'php:8.4', update: ''],
        [name: 'PHP 8.6', image: 'php:8.6', update: ''],
    ],
    nodeLabel: 'quick',
    nodeLabelFallback: 'general',
)

// Publishing runs only for `main` (never for a change request) and only after the builder checks above passed.
if (env.CHANGE_ID == null && env.BRANCH_NAME == 'main') {
    publishVariants(publishable)
}

/**
 * Checks every publishable variant, then syncs each into `main` of its repository (`bin/templates publish`).
 * Nothing is published unless every variant passed its checks. Publishing uses the Jenkins GitHub App credential `github-app-esoul`
 * (the one Composer uses): as username with password its password is an installation token, which must be allowed to push to
 * the template-* repositories (the App is installed there with Contents: write, and the credential's repository access is not
 * limited to this job's repository).
 */
def publishVariants(Map publishable) {
    def registry = 'rg.fr-par.scw.cloud/testing-images'
    def phpImage = "${registry}/php:8.5"
    def nodeImage = "${registry}/node:26"
    def checks = 'build/checks'

    // The container images of the checks, as the variants' own pipelines use them (`laravelPipeline`, `lsrPipeline`, `astroPipeline`).
    def images = [php: phpImage, node: nodeImage]

    // On the Quick node or a general one, like the checks above, so that a publish does not wait behind the long test
    // runs. On trial since 2026-11-23: Quick has one executor and is meant for builds of up to 15 minutes, and this step
    // keeps its own 90 minute limit while its real duration is measured. If it turns out to hold the node for long, it
    // goes back to a plain `node`.
    node('quick || general') {
        try {
            timeout(time: 90, unit: 'MINUTES') {
                String sourceCommit

                stage('Publish: checkout') {
                    // Large LFS assets are not needed by the build.
                    withEnv(['GIT_LFS_SKIP_SMUDGE=1']) {
                        sourceCommit = checkout(scm).GIT_COMMIT
                    }
                }

                stage('Publish: prepare') {
                    withCredentials([string(credentialsId: 'scaleway_secret_key', variable: 'SECRET')]) {
                        dockerRegistryLogin(registryUrl: registry, password: env.SECRET)
                    }
                    sh "docker pull ${phpImage} || true\ndocker pull ${nodeImage} || true"
                    composerInstall(image: phpImage)
                }

                // Variants are built into `build/checks` and checked there: the checks install dependencies into the
                // directory, so `publish` builds a pristine copy of its own.
                for (String name : publishable.keySet()) {
                    Map variant = publishable[name]
                    stage("Check ${name}") {
                        runCommandInTest(image: phpImage, command: "php bin/templates build ${name} --out=${checks} --commit=${sourceCommit}".toString())

                        for (Map check : variantChecks(variant)) {
                            runCommandInTest(
                                image: images[check.image],
                                workspace: "${env.WORKSPACE}/${checks}/${name}".toString(),
                                command: check.command,
                                env: check.env ?: [:],
                            )
                        }
                    }
                }

                stage('Publish variants') {
                    // A GitHub App credential yields an installation token as the password. It is passed to the container by
                    // name (its value is not part of the logged options); the user name (the App id) is not needed.
                    withCredentials([usernamePassword(
                        credentialsId: 'github-app-esoul',
                        usernameVariable: 'GITHUB_APP_ID',
                        passwordVariable: 'TEMPLATES_PUBLISH_TOKEN',
                    )]) {
                        for (String name : publishable.keySet()) {
                            runCommandInTest(
                                image: phpImage,
                                options: '-e TEMPLATES_PUBLISH_TOKEN',
                                command: "php bin/templates publish ${name} --commit=${sourceCommit}".toString(),
                            )
                        }
                    }
                }
            }
        } finally {
            sh 'sudo /usr/local/sbin/jenkins-fix-workspace "$WORKSPACE" || true'
            cleanWs()
        }
    }
}

/**
 * The checks of one publishable variant, run in order in its built directory: a list of [image: 'php' | 'node', command: ..., env: ...].
 * The commands are the ones the variant's own CI runs; a template has no lock file, so pnpm installs with `--no-frozen-lockfile`
 * (the audit stays unfiltered, as the dependencies guideline asks). A command is embedded in `docker run ... /bin/sh -c "<command>"`
 * by `runCommandInTest`: it must not hold a double quote, a dollar sign or a backtick.
 */
def variantChecks(Map variant) {
    def pnpmInstall = 'pnpm install --no-frozen-lockfile'
    def composerInstall = 'composer install --no-interaction --no-progress --optimize-autoloader'

    switch (variant.kind) {
        case 'laravel':
            // The JS checks of `laravelPipeline` (its defaults).
            def jsCommands = [
                full  : "${pnpmInstall} && pnpm audit && pnpm run eslint && pnpm run format:check && pnpm run tsc && pnpm run test --maxWorkers=1 --testTimeout=10000 && pnpm run build",
                assets: "${pnpmInstall} && pnpm audit && pnpm run build",
            ]
            def prepare = 'cp .env.example .env && php artisan key:generate --ansi && php artisan config:clear'
            if (variant.wayfinder) {
                prepare += ' && php artisan wayfinder:generate'
            }
            return [
                [image: 'php', command: "${composerInstall} && ${prepare} && composer run phpstan && composer run cs && composer run test".toString()],
                [image: 'node', command: jsCommands[variant.js].toString(), env: [WAYFINDER_TYPES_PREGENERATED: 'true', CI: 'true']],
            ]
        case 'lsr':
            // `lsrPipeline` defaults: Composer scripts phpstan:ci, cs, test; pnpm scripts eslint, format:check, tsc, test, build.
            return [
                [image: 'php', command: "${composerInstall} && composer phpstan:ci && composer cs && composer test".toString()],
                [image: 'node', command: "${pnpmInstall} && pnpm eslint && pnpm format:check && pnpm tsc && pnpm test && pnpm build".toString(), env: [CI: 'true']],
            ]
        case 'astro':
            // `astroPipeline` checks (every Astro variant has all of these scripts); the variant's `env` and `extra` commands come last.
            def commands = [pnpmInstall, 'pnpm eslint', 'pnpm format:check', 'pnpm check', 'pnpm test', 'pnpm build', 'pnpm verify']
            for (String extra : (variant.extra ?: [])) {
                commands << extra
            }
            return [
                [image: 'node', command: commands.join(' && '), env: [CI: 'true'] + (variant.env ?: [:])],
            ]
        case 'root':
            // Nothing to build: the script prints its usage (and exits 2) without arguments, and the README names the contract file.
            return [
                [image: 'php', command: "sh -n bin/add-submodules && sh bin/add-submodules 2>&1 | grep -q 'Usage: sh bin/add-submodules' && test -s docs/contract.md && grep -q 'docs/contract.md' README.md"],
            ]
        default:
            error("Unknown kind '${variant.kind}' of a publishable variant")
    }
}
