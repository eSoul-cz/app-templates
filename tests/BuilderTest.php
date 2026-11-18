<?php

declare(strict_types=1);

namespace ESoul\AppTemplates\Tests;

use ESoul\AppTemplates\Builder;
use ESoul\AppTemplates\Catalog;
use ESoul\AppTemplates\TemplateException;

final class BuilderTest extends BuilderTestCase
{
    public function testLaterLayersOverwriteEarlierFiles(): void
    {
        $this->put('layers/a/files/shared.txt', 'from a');
        $this->put('layers/a/files/only-a.txt', 'a');
        $this->put('layers/a/files/.hidden', 'dot');
        $this->put('layers/b/files/shared.txt', 'from b');
        $this->put('layers/b/files/nested/deep.txt', 'b');
        $this->variant([['layer' => 'a'], ['layer' => 'b']]);

        $out = $this->build();

        self::assertSame('from b', $this->read($out, 'shared.txt'));
        self::assertSame('a', $this->read($out, 'only-a.txt'));
        self::assertSame('dot', $this->read($out, '.hidden'));
        self::assertSame('b', $this->read($out, 'nested/deep.txt'));
    }

    public function testLayerOrderDecidesTheWinner(): void
    {
        $this->put('layers/a/files/x.txt', 'a');
        $this->put('layers/b/files/x.txt', 'b');
        $this->variant([['layer' => 'b'], ['layer' => 'a']]);

        self::assertSame('a', $this->read($this->build(), 'x.txt'));
    }

    public function testMergeDeepMergesObjectsAndUnionsScalarLists(): void
    {
        $this->put('layers/a/files/package.json', json_encode([
            'name' => 'demo',
            'scripts' => ['dev' => 'vite', 'build' => 'vite build'],
            'files' => ['a', 'b'],
            'workspaces' => [['name' => 'x']],
            'empty' => new \stdClass(),
        ]));
        $this->put('layers/b/merge/package.json', json_encode([
            'name' => 'renamed',
            'scripts' => ['build' => 'vite build --mode production', 'lint' => 'eslint .'],
            'files' => ['b', 'c'],
            'workspaces' => [['name' => 'y']],
            'engines' => ['node' => '>=24'],
        ]));
        $this->variant([['layer' => 'a'], ['layer' => 'b']]);

        $out = $this->build();
        $merged = $this->json($out, 'package.json');

        self::assertSame('renamed', $merged['name']);
        self::assertSame(['dev' => 'vite', 'build' => 'vite build --mode production', 'lint' => 'eslint .'], $merged['scripts']);
        self::assertSame(['a', 'b', 'c'], $merged['files']);
        self::assertSame([['name' => 'y']], $merged['workspaces']);
        self::assertSame(['node' => '>=24'], $merged['engines']);
        self::assertStringContainsString('"empty": {}', $this->read($out, 'package.json'));
    }

    public function testMergeCreatesMissingTargetAndSupportsNestedPaths(): void
    {
        $this->put('layers/a/merge/config/settings.json', '{"layers": ["general"]}');
        $this->put('layers/b/merge/config/settings.json', '{"layers": ["general", "framework/laravel"]}');
        $this->variant([['layer' => 'a'], ['layer' => 'b']]);

        self::assertSame(['layers' => ['general', 'framework/laravel']], $this->json($this->build(), 'config/settings.json'));
    }

    public function testMergeRejectsNonJsonFiles(): void
    {
        $this->put('layers/a/merge/notes.txt', 'nope');
        $this->variant([['layer' => 'a']]);

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('Only .json files can be merged');
        $this->build();
    }

    public function testAppendSkipsLinesAlreadyPresent(): void
    {
        $this->put('layers/a/files/.gitignore', "/vendor\n/node_modules\n");
        $this->put('layers/b/append/.gitignore', "/vendor\n\n# build\n/public/build\n/node_modules\n");
        $this->put('layers/c/append/.gitignore', "/public/build\n/storage/*.key\n");
        $this->put('layers/c/append/.env.example', "APP_NAME=Demo\n");
        $this->variant([['layer' => 'a'], ['layer' => 'b'], ['layer' => 'c']]);

        $out = $this->build();

        self::assertSame("/vendor\n/node_modules\n\n# build\n/public/build\n/storage/*.key\n", $this->read($out, '.gitignore'));
        self::assertSame("APP_NAME=Demo\n", $this->read($out, '.env.example'));
    }

    public function testDeleteTxtRemovesFilesGlobsAndDirectoriesBeforeTheLayerFiles(): void
    {
        $this->put('layers/a/files/keep.txt', 'keep');
        $this->put('layers/a/files/drop.txt', 'drop');
        $this->put('layers/a/files/tests/Unit/ExampleTest.php', 'x');
        $this->put('layers/a/files/tests/Feature/ExampleTest.php', 'x');
        $this->put('layers/a/files/resources/css/app.css', 'css');
        $this->put('layers/a/files/resources/js/app.js', 'js');
        $this->put('layers/b/delete.txt', "# comment\n\ndrop.txt\ntests/Unit\nresources/**/*.css\n");
        $this->put('layers/b/files/drop.txt', 'recreated');
        $this->variant([['layer' => 'a'], ['layer' => 'b']]);

        $out = $this->build();

        self::assertSame('keep', $this->read($out, 'keep.txt'));
        self::assertSame('recreated', $this->read($out, 'drop.txt'));
        self::assertDirectoryDoesNotExist($out . '/tests/Unit');
        self::assertFileExists($out . '/tests/Feature/ExampleTest.php');
        self::assertFileDoesNotExist($out . '/resources/css/app.css');
        self::assertFileExists($out . '/resources/js/app.js');
    }

    public function testIntoAndExclude(): void
    {
        $this->put('layers/root/files/README.md', 'root');
        $this->put('layers/app/files/Jenkinsfile', 'pipeline');
        $this->put('layers/app/files/.github/PULL_REQUEST_TEMPLATE.md', 'pr');
        $this->put('layers/app/files/.editorconfig', 'ec');
        $this->put('layers/app/files/src/index.php', 'code');
        $this->put('layers/app/merge/composer.json', '{"require": {"php": "^8.5"}}');
        $this->put('layers/app/append/.gitignore', "/vendor\n");
        $this->put('layers/app/delete.txt', "obsolete.txt\n");
        $this->variant([
            ['layer' => 'root'],
            ['layer' => 'app', 'into' => 'backend', 'exclude' => ['Jenkinsfile', '.github/**', '.editorconfig']],
        ]);

        $out = $this->build();

        self::assertSame('root', $this->read($out, 'README.md'));
        self::assertSame('code', $this->read($out, 'backend/src/index.php'));
        self::assertSame("/vendor\n", $this->read($out, 'backend/.gitignore'));
        self::assertSame(['php' => '^8.5'], $this->json($out, 'backend/composer.json')['require']);
        self::assertFileDoesNotExist($out . '/backend/Jenkinsfile');
        self::assertFileDoesNotExist($out . '/backend/.github');
        self::assertFileDoesNotExist($out . '/backend/.editorconfig');
        self::assertFileDoesNotExist($out . '/Jenkinsfile');
    }

    public function testRootComposerJsonIsStampedWithThePackageName(): void
    {
        $this->put('layers/a/files/composer.json', '{"name": "laravel/laravel", "description": "upstream", "type": "project", "require": {"php": "^8.5"}}');
        $this->variant([['layer' => 'a']]);

        $composer = $this->json($this->build(), 'composer.json');

        self::assertSame('esoul/template-demo', $composer['name']);
        self::assertSame('Demo variant.', $composer['description']);
        self::assertSame(['php' => '^8.5'], $composer['require']);
        self::assertSame('name', array_key_first($composer));
    }

    public function testComposerJsonIsCreatedWhenNoLayerShipsOne(): void
    {
        $this->put('layers/a/files/README.md', 'x');
        $this->variant([['layer' => 'a']]);

        $composer = $this->json($this->build(), 'composer.json');

        self::assertSame(
            ['name' => 'esoul/template-demo', 'description' => 'Demo variant.', 'type' => 'project', 'license' => 'MIT'],
            $composer,
        );
    }

    public function testReleaseWritesTemplateRecord(): void
    {
        $this->put('layers/a/files/x.txt', 'x');
        $this->variant([['layer' => 'a']]);

        $out = (new Builder(new Catalog($this->root)))->build('demo', $this->root . '/build', '1.2.3');

        self::assertSame(['variant' => 'demo', 'version' => '1.2.3'], $this->json($out, '.esoul/template.json'));
    }

    public function testBuildCleansTheTargetAndIsDeterministic(): void
    {
        $this->put('layers/a/files/x.txt', 'x');
        $this->put('layers/a/merge/data.json', '{"b": 1, "a": [1, 2]}');
        $this->variant([['layer' => 'a']]);

        $out = $this->build();
        file_put_contents($out . '/stale.txt', 'stale');
        $first = $this->read($out, 'data.json');
        $out = $this->build();

        self::assertFileDoesNotExist($out . '/stale.txt');
        self::assertSame($first, $this->read($out, 'data.json'));
    }

    public function testUnknownLayerFailsBeforeTouchingTheOutput(): void
    {
        $this->put('layers/a/files/x.txt', 'x');
        $this->variant([['layer' => 'a']], 'good');
        $out = $this->build('good');
        $this->variant([['layer' => 'a'], ['layer' => 'does/not-exist']], 'good');

        try {
            $this->build('good');
            self::fail('Expected TemplateException');
        } catch (TemplateException $e) {
            self::assertStringContainsString('Unknown layer "does/not-exist"', $e->getMessage());
        }
        self::assertFileExists($out . '/x.txt');
    }

    public function testUnknownVariantFails(): void
    {
        $this->variant([['layer' => 'a']]);

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('Unknown variant "missing". Available: demo.');
        $this->build('missing');
    }

    public function testVariantNameMustMatchFileName(): void
    {
        $this->put('variants/demo.json', '{"name": "other", "package": "esoul/x", "description": "d", "layers": [{"layer": "a"}]}');

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('is "other" but the file is called "demo.json"');
        $this->build();
    }

    public function testIntoMustNotEscapeTheVariant(): void
    {
        $this->put('layers/a/files/x.txt', 'x');
        $this->variant([['layer' => 'a', 'into' => '../outside']]);

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('"into" must be a relative path');
        $this->build();
    }
}
