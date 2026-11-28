<?php

declare(strict_types=1);

namespace ESoul\AppTemplates\Tests;

use ESoul\AppTemplates\AddonLoader;
use ESoul\AppTemplates\Builder;
use ESoul\AppTemplates\Catalog;
use ESoul\AppTemplates\Command\BuildCommand;
use ESoul\AppTemplates\GuidelinesRenderer;
use ESoul\AppTemplates\TemplateException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class AddonBuildTest extends AddonTestCase
{
    private const string LAYER = 'layers/addons/pulse/core';

    private function fixtures(): void {
        $this->manifest('pulse');
        $this->put('layers/base/files/bootstrap/providers.php', "return [\n    // @esoul-slot providers\n];\n");
        $this->put('layers/base/files/composer.json', '{"require": {"vendor/own": "^1"}}');
        $this->put('layers/base/merge/esoul-guidelines.json', '{"layers": ["general"]}');
        $this->put(self::LAYER . '/files/config/pulse.php', "addon\n");
        $this->put(self::LAYER . '/merge/composer.json', '{"require": {"laravel/pulse": "^1.4"}}');
        $this->put(self::LAYER . '/merge/esoul-guidelines.json', '{"layers": ["package/pulse"]}');
        $this->put(self::LAYER . '/insert/providers/bootstrap/providers.php', "Pulse::class,\n");
    }

    /** @param list<string> $addons */
    private function buildWithAddons(array $addons, string $out = 'build', ?GuidelinesRenderer $guidelines = null): string {
        $loader = new AddonLoader(new Catalog($this->root));
        $loaded = array_map($loader->load(...), $addons);

        return new Builder(new Catalog($this->root), $guidelines)->build('demo', $this->root . '/' . $out, self::COMMIT, $loaded);
    }

    public function test_build_with_an_addon_equals_build_plus_apply(): void {
        $this->fixtures();
        $plain = new Builder(new Catalog($this->root))->build('demo', $this->root . '/plain', self::COMMIT);
        $this->copyDir($plain, $this->root . '/applied');

        $built = $this->buildWithAddons(['pulse']);
        $tester = $this->applyTo('pulse', $this->root . '/applied');

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertSame($this->snapshot($this->root . '/applied'), $this->snapshot($built));
        self::assertSame([['name' => 'pulse']], $this->jsonAt($built, '.esoul/template.json', 'addons'));
    }

    public function test_addon_file_that_exists_in_the_build_is_an_error_naming_the_file(): void {
        $this->fixtures();
        $this->put('layers/base/files/config/pulse.php', "variant\n");

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageContaining('files/config/pulse.php already exists in the build');
        $this->buildWithAddons(['pulse']);
    }

    public function test_addon_file_identical_to_one_of_the_build_is_still_an_error(): void {
        $this->fixtures();
        $this->put('layers/base/files/config/pulse.php', "addon\n");

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageContaining('config/pulse.php');
        $this->buildWithAddons(['pulse']);
    }

    public function test_guidelines_are_rendered_after_the_addon_merged_its_layers(): void {
        $this->fixtures();
        $this->put('fake-guidelines', "<?php\nfile_put_contents('rendered.json', file_get_contents('esoul-guidelines.json'));\n");

        $out = $this->buildWithAddons(['pulse'], guidelines: new GuidelinesRenderer($this->root . '/fake-guidelines'));

        self::assertSame(['general', 'package/pulse'], $this->jsonAt($out, 'rendered.json', 'layers'));
    }

    public function test_the_record_is_written_with_the_addons_and_without_them_it_has_none(): void {
        $this->fixtures();

        $with = $this->buildWithAddons(['pulse'], 'with');
        $without = $this->buildWithAddons([], 'without');

        self::assertSame(['variant', 'commit', 'declined', 'addons'], array_keys($this->json($with, '.esoul/template.json')));
        self::assertSame(['variant', 'commit', 'declined'], array_keys($this->json($without, '.esoul/template.json')));
        self::assertSame("{\n    \"variant\": \"demo\",\n    \"commit\": \"abc1234\",\n    \"declined\": []\n}\n", $this->read($without, '.esoul/template.json'));
    }

    public function test_addons_need_a_commit_to_be_recorded(): void {
        $this->fixtures();
        $loader = new AddonLoader(new Catalog($this->root));

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageContaining('needs a source commit');
        new Builder(new Catalog($this->root))->build('demo', $this->root . '/build', null, [$loader->load('pulse')]);
    }

    public function test_build_command_applies_addons_and_refuses_an_undeclared_variant(): void {
        $this->fixtures();
        $this->put('fake-guidelines', "<?php\n");
        $this->put('variants/other.json', (string) json_encode(['name' => 'other', 'package' => 'esoul/template-other', 'description' => 'x', 'layers' => [['layer' => 'base']]]));

        $tester = new CommandTester(new BuildCommand($this->root));
        $tester->execute(['variant' => 'demo', '--addon' => ['pulse'], '--commit' => self::COMMIT, '--guidelines-bin' => 'fake-guidelines']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('// @esoul-addon pulse', $this->read($this->root . '/build/demo', 'bootstrap/providers.php'));

        $all = new CommandTester(new BuildCommand($this->root));
        $all->execute(['--all' => true, '--addon' => ['pulse'], '--commit' => self::COMMIT, '--out' => 'second', '--guidelines-bin' => 'fake-guidelines']);

        self::assertSame(Command::FAILURE, $all->getStatusCode());
        self::assertStringContainsString('does not support variant "other"', $this->display($all));
        self::assertDirectoryDoesNotExist($this->root . '/second');
    }

    public function test_several_addons_are_applied_in_order(): void {
        $this->fixtures();
        $this->manifest('second', ['demo' => ['addons/second/core']]);
        $this->put('layers/addons/second/core/insert/providers/bootstrap/providers.php', "Second::class,\n");

        $out = $this->buildWithAddons(['pulse', 'second']);

        self::assertSame(
            "return [\n    // @esoul-addon pulse\n    Pulse::class,\n    // @esoul-addon-end pulse\n    // @esoul-addon second\n    Second::class,\n    // @esoul-addon-end second\n    // @esoul-slot providers\n];\n",
            $this->read($out, 'bootstrap/providers.php'),
        );
        self::assertSame([['name' => 'pulse'], ['name' => 'second']], $this->jsonAt($out, '.esoul/template.json', 'addons'));
    }
}
