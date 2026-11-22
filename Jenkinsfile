@Library(['testing', 'dockerHelpers', 'pipelines']) _

// Checks the builder only, see README ("Usage" / CI). Publishing the variants is not implemented yet.
phpPackagePipeline(
    // A fixed --commit keeps the build independent of git inside the test container.
    additionalTests: [[name: 'Build all variants', command: 'php bin/templates build --all --commit=0000000']],
    matrix: [
        [name: 'PHP 8.4', image: 'php:8.4', update: ''],
        [name: 'PHP 8.6', image: 'php:8.6', update: ''],
    ],
)
