@Library(['testing', 'dockerHelpers', 'pipelines']) _

// eSoul's shared `phpPackagePipeline` step (library `pipelines`, eSoul-cz/jenkins-libraries): composer validate, PHPStan,
// PHP-CS-Fixer and composer audit, then the builder tests and a build of every variant on PHP 8.5, then the tests on 8.4 and
// 8.6 after Composer re-resolved the dependencies on that version. Publishing the variants is slice T7, not done here.
phpPackagePipeline(
    // A fixed --commit keeps the build independent of git inside the test container.
    additionalTests: [[name: 'Build all variants', command: 'php bin/templates build --all --commit=0000000']],
    matrix: [
        [name: 'PHP 8.4', image: 'php:8.4', update: ''],
        [name: 'PHP 8.6', image: 'php:8.6', update: ''],
    ],
)
