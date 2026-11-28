<?php

declare(strict_types=1);

namespace ESoul\AppTemplates\Tests;

use ESoul\AppTemplates\Command\AddonApplyCommand;
use ESoul\AppTemplates\CommandRunner;
use ESoul\AppTemplates\TemplateException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class AddonApplyTest extends AddonTestCase
{
    private const string LAYER = 'layers/addons/pulse/core';

    private function pulse(): void {
        $this->manifest('pulse');
    }

    public function test_files_are_created_kept_or_skipped_without_overwriting(): void {
        $this->pulse();
        $this->put(self::LAYER . '/files/config/pulse.php', "<?php\nreturn ['addon'];\n");
        $this->put(self::LAYER . '/files/docs/same.md', "same\n");
        $this->put(self::LAYER . '/files/docs/changed.md', "addon text\n");
        $project = $this->project(['docs/same.md' => "same\n", 'docs/changed.md' => "project text\n"]);

        $tester = $this->applyTo('pulse', $project);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame("<?php\nreturn ['addon'];\n", $this->read($project, 'config/pulse.php'));
        self::assertSame("project text\n", $this->read($project, 'docs/changed.md'));
        $display = $this->display($tester);
        self::assertStringContainsString('Created config/pulse.php', $display);
        self::assertStringContainsString('Kept (differs) docs/changed.md', $display);
        self::assertStringNotContainsString('docs/same.md', $display);
    }

    public function test_executable_bit_of_a_new_file_is_kept(): void {
        $this->pulse();
        $this->put(self::LAYER . '/files/bin/run', "#!/bin/sh\n");
        chmod($this->root . '/' . self::LAYER . '/files/bin/run', 0755);
        $project = $this->project();

        $this->applyTo('pulse', $project);

        self::assertTrue(is_executable($project . '/bin/run'));
    }

    public function test_merge_fills_missing_keys_and_keeps_existing_values(): void {
        $this->pulse();
        $this->put(self::LAYER . '/merge/composer.json', (string) json_encode([
            'require' => ['laravel/pulse' => '^1.4', 'laravel/framework' => '^13.0'],
            'extra' => ['laravel' => ['dont-discover' => ['a/b', 'c/d']]],
            'scripts' => ['post-update-cmd' => ['@php artisan pulse:install']],
        ]));
        $project = $this->project(['composer.json' => (string) json_encode([
            'require' => ['laravel/pulse' => '^1.2', 'php' => '^8.4'],
            'extra' => ['laravel' => ['dont-discover' => ['a/b']]],
            'scripts' => ['post-update-cmd' => 'echo project'],
        ], JSON_PRETTY_PRINT)]);

        $tester = $this->applyTo('pulse', $project);

        $composer = $this->json($project, 'composer.json');
        self::assertSame(['laravel/pulse' => '^1.2', 'php' => '^8.4', 'laravel/framework' => '^13.0'], $composer['require']);
        self::assertSame(['a/b', 'c/d'], $this->jsonAt($project, 'composer.json', 'extra', 'laravel', 'dont-discover'));
        self::assertSame('echo project', $this->jsonAt($project, 'composer.json', 'scripts', 'post-update-cmd'));
        $display = $this->display($tester);
        self::assertStringContainsString('Kept (differs) composer.json (require.laravel/pulse)', $display);
        self::assertStringContainsString('composer.json (scripts.post-update-cmd)', $display);
        self::assertStringContainsString('Merged composer.json', $display);
    }

    public function test_merge_never_overwrites_a_list_of_objects(): void {
        $this->pulse();
        $this->put(self::LAYER . '/merge/data.json', '{"items": [{"id": 2}]}');
        $project = $this->project(['data.json' => '{"items": [{"id": 1}], "other": 1}']);

        $tester = $this->applyTo('pulse', $project);

        self::assertSame([['id' => 1]], $this->jsonAt($project, 'data.json', 'items'));
        self::assertStringContainsString('Kept (differs) data.json (items)', $this->display($tester));
    }

    public function test_merge_keeps_the_indentation_of_the_file(): void {
        $this->pulse();
        $this->put(self::LAYER . '/merge/package.json', '{"dependencies": {"x": "^1.0"}, "scripts": {"pulse": "y"}}');
        $this->put(self::LAYER . '/merge/tabs.json', '{"b": {"c": 1}}');
        $this->put(self::LAYER . '/merge/new.json', '{"a": {"b": 1}}');
        $project = $this->project([
            'package.json' => "{\n  \"name\": \"p\",\n  \"scripts\": {\n    \"dev\": \"vite\"\n  }\n}\n",
            'tabs.json' => "{\n\t\"a\": 1\n}\n",
        ]);

        $this->applyTo('pulse', $project);

        self::assertSame(
            "{\n  \"name\": \"p\",\n  \"scripts\": {\n    \"dev\": \"vite\",\n    \"pulse\": \"y\"\n  },\n  \"dependencies\": {\n    \"x\": \"^1.0\"\n  }\n}\n",
            $this->read($project, 'package.json'),
        );
        self::assertSame("{\n\t\"a\": 1,\n\t\"b\": {\n\t\t\"c\": 1\n\t}\n}\n", $this->read($project, 'tabs.json'));
        self::assertSame("{\n    \"a\": {\n        \"b\": 1\n    }\n}\n", $this->read($project, 'new.json'));
    }

    public function test_merge_keeps_slashes_and_unicode_unescaped(): void {
        $this->pulse();
        $this->put(self::LAYER . '/merge/data.json', '{"url": "https://example.com/a", "text": "žluťoučký"}');
        $project = $this->project(['data.json' => "{\n  \"a\": 1\n}\n"]);

        $this->applyTo('pulse', $project);

        self::assertStringContainsString('"url": "https://example.com/a"', $this->read($project, 'data.json'));
        self::assertStringContainsString('žluťoučký', $this->read($project, 'data.json'));
    }

    public function test_append_adds_missing_lines_only(): void {
        $this->pulse();
        $this->put(self::LAYER . '/append/.env.example', "APP_NAME=Demo\nPULSE_ENABLED=true\n");
        $project = $this->project(['.env.example' => "APP_NAME=Demo\n"]);

        $tester = $this->applyTo('pulse', $project);

        self::assertSame("APP_NAME=Demo\nPULSE_ENABLED=true\n", $this->read($project, '.env.example'));
        self::assertStringContainsString('Appended .env.example', $this->display($tester));
    }

    public function test_insert_into_php_slot_with_indentation(): void {
        $this->pulse();
        $this->put(self::LAYER . '/insert/providers/bootstrap/providers.php', "\\App\\Domain\\Providers\\PulseServiceProvider::class,\n");
        $project = $this->project(['bootstrap/providers.php' => "<?php\n\nreturn [\n    App\\A::class,\n    // @esoul-slot providers\n];\n"]);

        $tester = $this->applyTo('pulse', $project);

        self::assertSame(
            "<?php\n\nreturn [\n    App\\A::class,\n    // @esoul-addon pulse\n    \\App\\Domain\\Providers\\PulseServiceProvider::class,\n    // @esoul-addon-end pulse\n    // @esoul-slot providers\n];\n",
            $this->read($project, 'bootstrap/providers.php'),
        );
        self::assertStringContainsString('Inserted bootstrap/providers.php (slot providers)', $this->display($tester));
    }

    public function test_insert_into_hash_slot(): void {
        $this->pulse();
        $this->put(self::LAYER . '/insert/supervisor-programs/docker/Dockerfile', "[program:pulse]\ncommand=php artisan pulse:check\n\nautostart=true\n");
        $project = $this->project(['docker/Dockerfile' => "FROM php\n# @esoul-slot supervisor-programs\nCMD x\n"]);

        $this->applyTo('pulse', $project);

        self::assertSame(
            "FROM php\n# @esoul-addon pulse\n[program:pulse]\ncommand=php artisan pulse:check\n\nautostart=true\n# @esoul-addon-end pulse\n# @esoul-slot supervisor-programs\nCMD x\n",
            $this->read($project, 'docker/Dockerfile'),
        );
    }

    public function test_insert_into_html_comment_slot_keeps_the_closing_syntax(): void {
        $this->pulse();
        $this->put(self::LAYER . '/insert/addons/README.md', "- [Pulse](docs/pulse.md)\n");
        $project = $this->project(['README.md' => "# P\n\n  <!-- @esoul-slot addons -->\n"]);

        $this->applyTo('pulse', $project);

        self::assertSame(
            "# P\n\n  <!-- @esoul-addon pulse -->\n  - [Pulse](docs/pulse.md)\n  <!-- @esoul-addon-end pulse -->\n  <!-- @esoul-slot addons -->\n",
            $this->read($project, 'README.md'),
        );
    }

    public function test_two_slots_of_one_file_each_get_the_block_once(): void {
        $this->pulse();
        $this->put(self::LAYER . '/insert/first/app.php', "one();\n");
        $this->put(self::LAYER . '/insert/second/app.php', "two();\n");
        $project = $this->project(['app.php' => "<?php\n// @esoul-slot first\nmiddle();\n// @esoul-slot second\n"]);

        $this->applyTo('pulse', $project);
        $once = $this->read($project, 'app.php');
        $tester = $this->applyTo('pulse', $project);

        self::assertSame(
            "<?php\n// @esoul-addon pulse\none();\n// @esoul-addon-end pulse\n// @esoul-slot first\nmiddle();\n// @esoul-addon pulse\ntwo();\n// @esoul-addon-end pulse\n// @esoul-slot second\n",
            $once,
        );
        self::assertSame($once, $this->read($project, 'app.php'));
        self::assertStringContainsString('Skipped app.php (slot first, block exists)', $this->display($tester));
    }

    public function test_insert_skips_when_the_block_exists_even_if_the_project_edited_it(): void {
        $this->pulse();
        $this->put(self::LAYER . '/insert/providers/p.php', "A::class,\n");
        $edited = "[\n    // @esoul-addon pulse\n    Edited::class,\n    // @esoul-addon-end pulse\n    // @esoul-slot providers\n]\n";
        $project = $this->project(['p.php' => $edited]);

        $this->applyTo('pulse', $project);

        self::assertSame($edited, $this->read($project, 'p.php'));
    }

    public function test_a_block_of_another_addon_does_not_count(): void {
        $this->pulse();
        $this->put(self::LAYER . '/insert/providers/p.php', "A::class,\n");
        $project = $this->project(['p.php' => "// @esoul-addon pulse-extra\n// @esoul-slot providers\n"]);

        $this->applyTo('pulse', $project);

        self::assertStringContainsString("// @esoul-addon pulse\nA::class,\n// @esoul-addon-end pulse\n// @esoul-slot providers", $this->read($project, 'p.php'));
    }

    public function test_missing_slot_refuses_before_writing_and_names_the_changelog_entry(): void {
        $this->pulse();
        $this->put(self::LAYER . '/files/new.txt', 'new');
        $this->put(self::LAYER . '/insert/providers/bootstrap/providers.php', "A::class,\n");
        $this->put('CHANGELOG.md', "# Changelog\n\n## Unreleased\n\n### 2026-11-28: Add addon slots\n\nAffects: layer `laravel/base`.\n\nAdds slot `providers` to `bootstrap/providers.php`.\n\n### 2026-11-20: Older\n\nNothing.\n");
        $project = $this->project(['bootstrap/providers.php' => "<?php\nreturn [];\n"]);
        $before = $this->snapshot($project);

        $tester = $this->applyTo('pulse', $project);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        $display = $this->display($tester);
        self::assertStringContainsString('slot "providers" is missing in bootstrap/providers.php', $display);
        self::assertStringContainsString('"Add addon slots"', $display);
        self::assertSame($before, $this->snapshot($project));
    }

    public function test_missing_slot_without_a_changelog_entry_still_refuses(): void {
        $this->pulse();
        $this->put(self::LAYER . '/insert/providers/missing.php', "A::class,\n");
        $project = $this->project();

        $tester = $this->applyTo('pulse', $project);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('slot "providers" is missing in missing.php', $this->display($tester));
    }

    public function test_duplicate_slot_line_is_an_error(): void {
        $this->pulse();
        $this->put(self::LAYER . '/insert/providers/p.php', "A::class,\n");
        $project = $this->project(['p.php' => "// @esoul-slot providers\nx\n// @esoul-slot providers\n"]);

        $tester = $this->applyTo('pulse', $project);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('slot "providers" appears on 2 lines of p.php', $this->display($tester));
    }

    public function test_slot_names_are_word_bounded(): void {
        $this->pulse();
        $this->put(self::LAYER . '/insert/routes/r.php', "a();\n");
        $project = $this->project(['r.php' => "// @esoul-slot routes-extra\n// @esoul-slot routes\n"]);

        $this->applyTo('pulse', $project);

        self::assertSame("// @esoul-slot routes-extra\n// @esoul-addon pulse\na();\n// @esoul-addon-end pulse\n// @esoul-slot routes\n", $this->read($project, 'r.php'));
    }

    public function test_project_changes_are_kept_and_the_addon_part_is_added(): void {
        $this->pulse();
        $this->put(self::LAYER . '/files/config/pulse.php', "addon\n");
        $this->put(self::LAYER . '/merge/composer.json', '{"require": {"laravel/pulse": "^1.4"}, "scripts": {"post-update-cmd": ["pulse"]}}');
        $this->put(self::LAYER . '/insert/providers/bootstrap/providers.php', "Pulse::class,\n");
        $project = $this->project([
            'config/pulse.php' => "project edit\n",
            'composer.json' => "{\n  \"require\": {\n    \"vendor/own\": \"^2.0\"\n  },\n  \"scripts\": {\n    \"post-update-cmd\": [\"mine\"]\n  }\n}\n",
            'bootstrap/providers.php' => "return [\n    Own::class,\n    // @esoul-slot providers\n];\n",
        ]);

        $this->applyTo('pulse', $project);

        self::assertSame("project edit\n", $this->read($project, 'config/pulse.php'));
        self::assertSame(['vendor/own' => '^2.0', 'laravel/pulse' => '^1.4'], $this->jsonAt($project, 'composer.json', 'require'));
        self::assertSame(['mine', 'pulse'], $this->jsonAt($project, 'composer.json', 'scripts', 'post-update-cmd'));
        self::assertStringContainsString("Own::class,\n    // @esoul-addon pulse\n    Pulse::class,", $this->read($project, 'bootstrap/providers.php'));
    }

    public function test_applying_twice_changes_nothing(): void {
        $this->pulse();
        $this->put(self::LAYER . '/files/config/pulse.php', "addon\n");
        $this->put(self::LAYER . '/merge/composer.json', '{"require": {"laravel/pulse": "^1.4"}}');
        $this->put(self::LAYER . '/merge/esoul-guidelines.json', '{"layers": ["package/pulse"]}');
        $this->put(self::LAYER . '/append/.env.example', "PULSE=1\n");
        $this->put(self::LAYER . '/insert/providers/p.php', "Pulse::class,\n");
        $project = $this->project([
            'composer.json' => "{\n  \"name\": \"p/p\"\n}\n",
            'esoul-guidelines.json' => "{\n    \"layers\": [\"general\"]\n}\n",
            '.env.example' => "APP=1\n",
            'p.php' => "    // @esoul-slot providers\n",
        ]);

        $this->applyTo('pulse', $project);
        $first = $this->snapshot($project);
        $tester = $this->applyTo('pulse', $project);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame($first, $this->snapshot($project));
        self::assertStringNotContainsString('Created', $this->display($tester));
        self::assertStringNotContainsString('Merged', $this->display($tester));
        self::assertStringNotContainsString('Inserted', $this->display($tester));
    }

    public function test_undeclared_variant_is_refused_naming_the_supported_variants(): void {
        $this->put('variants/other.json', (string) json_encode(['name' => 'other', 'package' => 'esoul/template-other', 'description' => 'x', 'layers' => [['layer' => 'base']]]));
        $this->manifest('pulse', ['demo' => ['addons/pulse/core'], 'other' => ['addons/pulse/core']]);
        $this->put(self::LAYER . '/files/a.txt', 'a');
        $project = $this->project(variant: 'astro-static');

        $tester = $this->applyTo('pulse', $project);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        $display = $this->display($tester);
        self::assertStringContainsString('does not support variant "astro-static"', $display);
        self::assertStringContainsString('demo, other', $display);
        self::assertFileDoesNotExist($project . '/a.txt');
    }

    public function test_project_without_a_record_is_refused(): void {
        $this->pulse();
        $this->put(self::LAYER . '/files/a.txt', 'a');
        $this->put('project/composer.json', '{}');

        $tester = $this->applyTo('pulse', $this->root . '/project');

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('has no .esoul/template.json', $this->display($tester));
        self::assertFileDoesNotExist($this->root . '/project/a.txt');
    }

    public function test_record_lists_an_addon_of_the_repository_once(): void {
        $this->pulse();
        $this->put(self::LAYER . '/files/a.txt', 'a');
        $project = $this->project();

        $this->applyTo('pulse', $project);
        $this->applyTo('pulse', $project);

        self::assertSame(
            "{\n    \"variant\": \"demo\",\n    \"commit\": \"abc1234\",\n    \"declined\": [],\n    \"addons\": [\n        {\n            \"name\": \"pulse\"\n        }\n    ]\n}\n",
            $this->read($project, '.esoul/template.json'),
        );
    }

    public function test_record_of_an_external_addon_has_source_and_commit_and_replaces_the_entry(): void {
        $this->put('ext/addon.json', (string) json_encode(['name' => 'acme', 'description' => 'x', 'variants' => ['demo' => ['own']], 'steps' => ['Set ACME_KEY']]));
        $this->put('ext/layers/own/files/acme.txt', 'acme');
        $commit = $this->commitAll('ext');
        $project = $this->project();
        $this->put('project/.esoul/template.json', (string) json_encode(['variant' => 'demo', 'commit' => self::COMMIT, 'declined' => [], 'addons' => [['name' => 'acme', 'source' => 'old', 'commit' => 'old'], ['name' => 'pulse']]]));

        $tester = $this->applyTo($this->root . '/ext', $project);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame(
            [['name' => 'acme', 'source' => $this->root . '/ext', 'commit' => $commit], ['name' => 'pulse']],
            $this->jsonAt($project, '.esoul/template.json', 'addons'),
        );
        self::assertSame('acme', $this->read($project, 'acme.txt'));
        self::assertStringContainsString('Set ACME_KEY', $this->display($tester));
    }

    public function test_external_addon_from_a_plain_directory_records_no_commit(): void {
        $this->put('ext/addon.json', (string) json_encode(['name' => 'acme', 'description' => 'x', 'variants' => ['demo' => ['own']]]));
        $this->put('ext/layers/own/files/acme.txt', 'acme');
        $project = $this->project();

        $this->applyTo($this->root . '/ext', $project);

        self::assertSame([['name' => 'acme', 'source' => $this->root . '/ext']], $this->jsonAt($project, '.esoul/template.json', 'addons'));
    }

    public function test_external_addon_is_cloned_from_a_git_url(): void {
        $this->put('ext/addon.json', (string) json_encode(['name' => 'acme', 'description' => 'x', 'variants' => ['demo' => ['own']]]));
        $this->put('ext/layers/own/files/acme.txt', 'acme');
        $commit = $this->commitAll('ext');
        $project = $this->project();
        $url = 'file://' . $this->root . '/ext';

        $tester = $this->applyTo($url, $project);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertSame([['name' => 'acme', 'source' => $url, 'commit' => $commit]], $this->jsonAt($project, '.esoul/template.json', 'addons'));
        self::assertSame('acme', $this->read($project, 'acme.txt'));
    }

    public function test_addon_placeholder_becomes_the_source_of_an_external_addon(): void {
        $this->put('ext/addon.json', (string) json_encode(['name' => 'acme', 'description' => 'x', 'variants' => ['demo' => ['own']]]));
        $this->put('ext/layers/own/merge/esoul-guidelines.json', '{"sources": [{"name": "acme", "url": "@addon"}], "list": ["@addon"]}');
        $project = $this->project(['esoul-guidelines.json' => '{"layers": ["general"]}']);

        $this->applyTo($this->root . '/ext', $project);

        $config = $this->json($project, 'esoul-guidelines.json');
        self::assertSame([['name' => 'acme', 'url' => $this->root . '/ext']], $config['sources']);
        self::assertSame([$this->root . '/ext'], $config['list']);
    }

    public function test_install_steps_update_only_the_added_packages(): void {
        $this->pulse();
        $this->put(self::LAYER . '/merge/composer.json', '{"require": {"laravel/pulse": "^1.4", "php": "^8.4", "ext-json": "*", "vendor/own": "^1"}, "require-dev": {"vendor/dev": "^1"}}');
        $this->put(self::LAYER . '/merge/package.json', '{"dependencies": {"x": "^1"}}');
        $project = $this->project([
            'composer.json' => '{"require": {"vendor/own": "^1"}}',
            'composer.lock' => '{}',
            'package.json' => '{}',
            'pnpm-lock.yaml' => '',
            'esoul-guidelines.json' => '{"layers": ["general"]}',
        ]);

        $tester = $this->applyTo('pulse', $project, install: true);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertSame(['composer', 'update', 'laravel/pulse', 'vendor/dev', '-W'], $this->runner->commands[0]);
        self::assertSame(['pnpm', 'install'], $this->runner->commands[1]);
        self::assertSame('sync', $this->runner->commands[2][2]);
        self::assertSame('--no-interaction', $this->runner->commands[2][3]);
        self::assertCount(3, $this->runner->commands);
    }

    public function test_install_steps_need_lock_files_and_changes(): void {
        $this->pulse();
        $this->put(self::LAYER . '/merge/composer.json', '{"require": {"laravel/pulse": "^1.4"}}');
        $project = $this->project(['composer.json' => '{}']);

        $this->applyTo('pulse', $project, install: true);

        self::assertSame([], $this->runner->commands);
    }

    public function test_project_guidelines_installer_is_preferred(): void {
        $this->pulse();
        $this->put(self::LAYER . '/files/a.txt', 'a');
        $project = $this->project(['esoul-guidelines.json' => '{}', 'vendor/bin/esoul-guidelines' => "<?php\n"]);

        $this->applyTo('pulse', $project, install: true);

        self::assertSame($project . '/vendor/bin/esoul-guidelines', $this->runner->commands[0][1]);
    }

    public function test_no_install_runs_nothing(): void {
        $this->pulse();
        $this->put(self::LAYER . '/merge/composer.json', '{"require": {"laravel/pulse": "^1.4"}}');
        $project = $this->project(['composer.json' => '{}', 'composer.lock' => '{}', 'esoul-guidelines.json' => '{}']);

        $this->applyTo('pulse', $project);

        self::assertSame([], $this->runner->commands);
    }

    public function test_steps_are_printed_after_the_report(): void {
        $this->manifest('pulse', extra: ['steps' => ['php artisan migrate', 'Set PULSE_KEY']]);
        $this->put(self::LAYER . '/files/a.txt', 'a');

        $display = $this->display($this->applyTo('pulse', $this->project()));

        self::assertStringContainsString('Next steps', $display);
        self::assertGreaterThan(strpos($display, 'Created'), strpos($display, 'php artisan migrate'));
        self::assertStringContainsString('Set PULSE_KEY', $display);
    }

    public function test_warns_when_the_changelog_changed_since_the_project_commit(): void {
        $this->pulse();
        $this->put(self::LAYER . '/files/a.txt', 'a');
        $this->put('CHANGELOG.md', "# Changelog\n");
        $old = $this->commitAll('');
        $this->put('CHANGELOG.md', "# Changelog\n\nnew entry\n");
        $this->git($this->root, 'add', '-A');
        $this->git($this->root, 'commit', '-q', '-m', 'changelog');
        $project = $this->project();
        $this->put('project/.esoul/template.json', (string) json_encode(['variant' => 'demo', 'commit' => $old, 'declined' => []]));

        $tester = $this->applyTo('pulse', $project);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('CHANGELOG.md changed since', $this->display($tester));
        self::assertFileExists($project . '/a.txt');
    }

    public function test_warns_when_the_project_commit_is_unknown_here(): void {
        $this->pulse();
        $this->put(self::LAYER . '/files/a.txt', 'a');
        $this->commitAll('');
        $project = $this->project();

        $tester = $this->applyTo('pulse', $project);

        self::assertStringContainsString('is unknown in this checkout', $this->display($tester));
        self::assertFileExists($project . '/a.txt');
    }

    public function test_no_warning_when_the_changelog_is_unchanged(): void {
        $this->pulse();
        $this->put(self::LAYER . '/files/a.txt', 'a');
        $old = $this->commitAll('');
        $this->put('other.txt', 'x');
        $this->git($this->root, 'add', '-A');
        $this->git($this->root, 'commit', '-q', '-m', 'other');
        $project = $this->project();
        $this->put('project/.esoul/template.json', (string) json_encode(['variant' => 'demo', 'commit' => $old, 'declined' => []]));

        $tester = $this->applyTo('pulse', $project);

        self::assertStringNotContainsString('CHANGELOG', $this->display($tester));
        self::assertStringNotContainsString('unknown', $this->display($tester));
    }

    public function test_command_reports_a_failing_install_step_after_applying(): void {
        $this->pulse();
        $this->put(self::LAYER . '/files/a.txt', 'a');
        $project = $this->project(['esoul-guidelines.json' => '{}']);
        $runner = new class implements CommandRunner {
            public function run(array $command, string $directory): void {
                throw new TemplateException('boom');
            }
        };

        $tester = new CommandTester(new AddonApplyCommand($this->root, $runner));
        $tester->execute(['addon' => 'pulse', 'project' => $project]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('applied and recorded, but an install step failed: boom', $this->display($tester));
        self::assertFileExists($project . '/a.txt');
    }
}
