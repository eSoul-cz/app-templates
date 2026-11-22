<?php

declare(strict_types=1);

namespace ESoul\AppTemplates\Tests;

use ESoul\AppTemplates\Command\PublishCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Process\Process;

final class PublishCommandTest extends BuilderTestCase
{
    private string $remote;

    protected function setUp(): void {
        parent::setUp();
        $this->remote = $this->root . '/remote.git';
        new Process(['git', 'init', '--quiet', '--bare', $this->remote])->mustRun();
        $this->put('fake-guidelines', "<?php\n");
        $this->put('layers/a/files/x.txt', 'x');
        $this->put('layers/a/merge/esoul-guidelines.json', '{"layers": ["general"]}');
        $this->variant([['layer' => 'a']]);
    }

    /** @param array<string, string|bool> $options */
    private function runPublish(array $options): CommandTester {
        $tester = new CommandTester(new PublishCommand($this->root));
        $tester->execute(['variant' => 'demo', '--guidelines-bin' => 'fake-guidelines', '--repo' => $this->remote, ...$options]);

        return $tester;
    }

    public function test_it_builds_and_pushes_with_the_given_message_and_commit(): void {
        $tester = $this->runPublish(['--commit' => 'abc1234', '--message' => ':bug: Fix a thing']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Created', $tester->getDisplay());
        $log = new Process(['git', '--git-dir=' . $this->remote, 'log', '-1', '--format=%s', 'main']);
        $log->mustRun();
        self::assertSame(':bug: Fix a thing', trim($log->getOutput()));
        $recorded = new Process(['git', '--git-dir=' . $this->remote, 'show', 'main:.esoul/template.json']);
        $recorded->mustRun();
        self::assertStringContainsString('abc1234', $recorded->getOutput());
        self::assertDirectoryDoesNotExist($this->root . '/build');
    }

    public function test_dry_run_reports_and_pushes_nothing(): void {
        $tester = $this->runPublish(['--commit' => 'abc1234', '--dry-run' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Dry run, would push', $tester->getDisplay());
        $refs = new Process(['git', '--git-dir=' . $this->remote, 'for-each-ref']);
        $refs->mustRun();
        self::assertSame('', trim($refs->getOutput()));
    }

    public function test_without_a_source_commit_it_refuses(): void {
        $tester = $this->runPublish([]);

        self::assertSame(Command::INVALID, $tester->getStatusCode());
        self::assertStringContainsString('source commit is unknown', $tester->getDisplay());
    }

    public function test_an_unknown_variant_fails(): void {
        $tester = new CommandTester(new PublishCommand($this->root));
        $tester->execute(['variant' => 'nope', '--commit' => 'abc1234']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Unknown variant', $tester->getDisplay());
    }
}
