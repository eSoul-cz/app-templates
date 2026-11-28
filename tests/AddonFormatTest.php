<?php

declare(strict_types=1);

namespace ESoul\AppTemplates\Tests;

use ESoul\AppTemplates\AddonLoader;
use ESoul\AppTemplates\Builder;
use ESoul\AppTemplates\Catalog;
use ESoul\AppTemplates\PhpFormatter;
use Symfony\Component\Console\Command\Command;

/** The formatter that runs on the PHP files an addon inserted into (offline: the runner only records). */
final class AddonFormatTest extends AddonTestCase
{
    private const string LAYER = 'layers/addons/pulse/core';

    private const string PROVIDERS = "<?php\n\nreturn [\n    // @esoul-slot providers\n];\n";

    private function fixtures(): void {
        $this->manifest('pulse');
        $this->put(self::LAYER . '/insert/providers/bootstrap/providers.php', "\\App\\PulseProvider::class,\n");
    }

    /**
     * @param list<list<string>> $commands
     *
     * @return list<list<string>> the commands without their `--cache-file=<own file of the pass>` option, which each must have
     */
    private function withoutCacheFile(array $commands): array {
        $plain = [];
        foreach ($commands as $command) {
            $kept = array_values(array_filter($command, static fn (string $part): bool => ! str_starts_with($part, '--cache-file=')));
            self::assertCount(count($command) - 1, $kept, 'every pass has its own cache file');
            $plain[] = $kept;
        }
        self::assertCount(count($commands), array_unique(array_column($commands, count($commands[0]) - 2)), 'no two passes share a cache file');

        return $plain;
    }

    /** @return list<list<string>> the commands that run Pint (a script called `.../vendor/bin/pint`) */
    private function pintCommands(): array {
        return array_values(array_filter(
            $this->runner->commands,
            static fn (array $command): bool => isset($command[1]) && str_ends_with($command[1], '/vendor/bin/pint'),
        ));
    }

    public function test_the_projects_own_pint_runs_on_exactly_the_inserted_php_files(): void {
        $this->fixtures();
        $project = $this->project(['bootstrap/providers.php' => self::PROVIDERS, 'README.md' => "# P\n\n<!-- @esoul-slot docs -->\n", 'vendor/bin/pint' => "<?php\n"]);

        $tester = $this->applyTo('pulse', $project);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertSame(array_fill(0, PhpFormatter::PASSES, [PHP_BINARY, $project . '/vendor/bin/pint', 'bootstrap/providers.php']), $this->withoutCacheFile($this->runner->commands));
        self::assertStringContainsString('Formatted bootstrap/providers.php', $this->display($tester));
    }

    public function test_without_vendor_pint_a_temporary_one_formats_with_the_projects_pint_json(): void {
        $this->fixtures();
        $project = $this->project(['bootstrap/providers.php' => self::PROVIDERS, 'pint.json' => '{"preset": "laravel"}']);

        $tester = $this->applyTo('pulse', $project);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertSame('composer', $this->runner->commands[0][0]);
        self::assertSame(['require', 'laravel/pint'], array_slice($this->runner->commands[0], 1, 2));
        $pint = $this->pintCommands();
        self::assertSame(array_fill(0, PhpFormatter::PASSES, [PHP_BINARY, $pint[0][1], '--config', $project . '/pint.json', 'bootstrap/providers.php']), $this->withoutCacheFile($pint));
        self::assertStringContainsString('Formatted bootstrap/providers.php', $this->display($tester));
    }

    public function test_nothing_runs_without_pint_json_and_vendor_pint(): void {
        $this->fixtures();
        $project = $this->project(['bootstrap/providers.php' => self::PROVIDERS]);

        $tester = $this->applyTo('pulse', $project);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame([], $this->runner->commands);
        self::assertStringNotContainsString('Formatted', $this->display($tester));
    }

    public function test_an_insert_into_a_file_that_is_not_php_does_not_run_the_formatter(): void {
        $this->manifest('pulse');
        $this->put(self::LAYER . '/insert/pipeline-options/Jenkinsfile', "key: 'value',\n");
        $this->put(self::LAYER . '/insert/docs/README.md', "- Pulse\n");
        $project = $this->project([
            'Jenkinsfile' => "pipeline(\n    // @esoul-slot pipeline-options\n)\n",
            'README.md' => "# P\n\n<!-- @esoul-slot docs -->\n",
            'pint.json' => '{}',
            'vendor/bin/pint' => "<?php\n",
        ]);

        $tester = $this->applyTo('pulse', $project);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('Jenkinsfile (slot pipeline-options)', $this->display($tester));
        self::assertSame([], $this->runner->commands);
    }

    public function test_a_second_apply_inserts_nothing_and_runs_no_formatter(): void {
        $this->fixtures();
        $project = $this->project(['bootstrap/providers.php' => self::PROVIDERS, 'pint.json' => '{}']);
        $this->applyTo('pulse', $project);
        $afterFirst = $this->snapshot($project);
        $this->runner->commands = [];

        $tester = $this->applyTo('pulse', $project);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame([], $this->runner->commands);
        self::assertSame($afterFirst, $this->snapshot($project));
        self::assertStringContainsString('block exists', $this->display($tester));
    }

    public function test_a_failing_formatter_is_an_error_that_names_the_files_and_says_the_addon_was_applied(): void {
        $this->fixtures();
        $project = $this->project(['bootstrap/providers.php' => self::PROVIDERS, 'vendor/bin/pint' => "<?php\n"]);
        $this->runner->failure = 'exit 1';

        $tester = $this->applyTo('pulse', $project);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        $display = $this->display($tester);
        self::assertStringContainsString('applied and recorded', $display);
        self::assertStringContainsString('bootstrap/providers.php', $display);
        self::assertStringContainsString('\\App\\PulseProvider::class', $this->read($project, 'bootstrap/providers.php'));
        self::assertSame([['name' => 'pulse']], $this->jsonAt($project, '.esoul/template.json', 'addons'));
    }

    public function test_a_build_formats_the_inserted_php_with_the_builds_pint_json(): void {
        $this->fixtures();
        $this->put('layers/base/files/bootstrap/providers.php', self::PROVIDERS);
        $this->put('layers/base/files/pint.json', '{"preset": "laravel"}');
        $loaded = [new AddonLoader(new Catalog($this->root))->load('pulse')];

        $built = new Builder(new Catalog($this->root), null, new PhpFormatter($this->runner))->build('demo', $this->root . '/build', self::COMMIT, $loaded);

        $pint = $this->pintCommands();
        self::assertSame(array_fill(0, PhpFormatter::PASSES, [PHP_BINARY, $pint[0][1], '--config', $built . '/pint.json', 'bootstrap/providers.php']), $this->withoutCacheFile($pint));
    }

    public function test_a_build_without_addons_runs_no_formatter(): void {
        $this->put('layers/base/files/pint.json', '{}');

        new Builder(new Catalog($this->root), null, new PhpFormatter($this->runner))->build('demo', $this->root . '/build', self::COMMIT);

        self::assertSame([], $this->runner->commands);
    }
}
