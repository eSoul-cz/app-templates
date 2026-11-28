<?php

declare(strict_types=1);

namespace ESoul\AppTemplates\Tests;

use ESoul\AppTemplates\Command\AddonApplyCommand;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

/** Fixtures for addon tests: a repository root with one variant `demo`, addons under `addons/`, and a project directory. */
abstract class AddonTestCase extends BuilderTestCase
{
    protected const string COMMIT = 'abc1234';

    protected RecordingRunner $runner;

    protected function setUp(): void {
        parent::setUp();
        putenv('COLUMNS=1000');
        $this->variant([['layer' => 'base']]);
        $this->put('layers/base/files/README.md', "# Demo\n");
        $this->runner = new RecordingRunner();
    }

    protected function tearDown(): void {
        putenv('COLUMNS');
        parent::tearDown();
    }

    /**
     * @param array<string, list<string>> $variants
     * @param array<string, mixed>        $extra
     */
    protected function manifest(string $name, array $variants = ['demo' => ['addons/pulse/core']], array $extra = []): void {
        $this->put('addons/' . $name . '.json', (string) json_encode([
            'name' => $name,
            'description' => 'Demo addon.',
            'variants' => $variants,
            ...$extra,
        ], JSON_UNESCAPED_SLASHES));
    }

    /** @param array<string, string> $files relative path => content, written into `project/` */
    protected function project(array $files = [], string $variant = 'demo'): string {
        $this->put('project/.esoul/template.json', (string) json_encode(['variant' => $variant, 'commit' => self::COMMIT, 'declined' => []], JSON_PRETTY_PRINT));
        foreach ($files as $relative => $content) {
            $this->put('project/' . $relative, $content);
        }

        return $this->root . '/project';
    }

    protected function applyTo(string $addon, string $project, bool $install = false): CommandTester {
        $tester = new CommandTester(new AddonApplyCommand($this->root, $this->runner));
        $tester->execute(['addon' => $addon, 'project' => $project, '--guidelines-bin' => 'fake-guidelines', ...($install ? [] : ['--no-install' => true])]);

        return $tester;
    }

    protected function display(CommandTester $tester): string {
        return (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
    }

    /** @return array<string, string> relative path => content of every file below a directory */
    protected function snapshot(string $dir): array {
        $files = [];
        foreach (new Finder()->files()->in($dir)->ignoreDotFiles(false)->ignoreVCS(true) as $file) {
            $files[$file->getRelativePathname()] = $file->getContents();
        }
        ksort($files);

        return $files;
    }

    protected function git(string $dir, string ...$args): string {
        $git = new Process(['git', '-c', 'user.name=t', '-c', 'user.email=t@example.com', ...$args], $dir);
        $git->mustRun();

        return trim($git->getOutput());
    }

    /** Make a directory of the fixture root a git repository with one commit and return the commit. */
    protected function commitAll(string $relative): string {
        $dir = $this->root . '/' . $relative;
        $this->git($dir, 'init', '-q');
        $this->git($dir, 'add', '-A');
        $this->git($dir, 'commit', '-q', '-m', 'init');

        return $this->git($dir, 'rev-parse', 'HEAD');
    }

    protected function copyDir(string $from, string $to): void {
        new Filesystem()->mirror($from, $to);
    }
}
