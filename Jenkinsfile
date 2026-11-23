@Library(['testing', 'dockerHelpers', 'pipelines']) _

// The variants that are published to eSoul-cz/template-<variant> from `main`, in one place. `wayfinder` says
// whether the variant has Laravel Wayfinder (routes are generated before the JS build) and `js` which JS checks
// its own CI runs: `full` (eslint, prettier, typecheck, tests, build) or `assets` (the asset build only).
// The LSR and Astro variants (`lsrPipeline` and `astroPipeline` exist) are added here only together with a check stage for their own CI:
// the `Check <variant>` stage below runs the Laravel checks (artisan, Wayfinder), which they do not have (README, "Publishing flow").
def publishable = [
    'laravel-inertia'         : [wayfinder: true, js: 'full'],
    'laravel-filament'        : [wayfinder: false, js: 'assets'],
    'laravel-inertia-filament': [wayfinder: true, js: 'full'],
    'laravel-lunar'           : [wayfinder: true, js: 'full'],
]

// The builder only: composer validation, PHPStan, code style, audit and tests, and `build --all` on every pull request.
// A fixed --commit keeps that build independent of git inside the test container.
phpPackagePipeline(
    additionalTests: [[name: 'Build all variants', command: 'php bin/templates build --all --commit=0000000']],
    matrix: [
        [name: 'PHP 8.4', image: 'php:8.4', update: ''],
        [name: 'PHP 8.6', image: 'php:8.6', update: ''],
    ],
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

    // The JS checks of the variants' own CI (`laravelPipeline` defaults), with the audit unfiltered as the dependencies guideline asks.
    def jsCommands = [
        full  : 'pnpm install --no-frozen-lockfile && pnpm audit && pnpm run eslint && pnpm run format:check && pnpm run tsc && pnpm run test --maxWorkers=1 --testTimeout=10000 && pnpm run build',
        assets: 'pnpm install --no-frozen-lockfile && pnpm audit && pnpm run build',
    ]

    node {
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

                        def prepare = 'cp .env.example .env && php artisan key:generate --ansi && php artisan config:clear'
                        if (variant.wayfinder) {
                            prepare += ' && php artisan wayfinder:generate'
                        }
                        runCommandInTest(
                            image: phpImage,
                            workspace: "${env.WORKSPACE}/${checks}/${name}".toString(),
                            command: "composer install --no-interaction --no-progress --optimize-autoloader && ${prepare} && composer run phpstan && composer run cs && composer run test".toString(),
                        )
                        runCommandInTest(
                            image: nodeImage,
                            workspace: "${env.WORKSPACE}/${checks}/${name}".toString(),
                            command: jsCommands[variant.js],
                            env: [WAYFINDER_TYPES_PREGENERATED: 'true', CI: 'true'],
                        )
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
