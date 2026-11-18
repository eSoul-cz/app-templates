<?php

declare(strict_types=1);

namespace ESoul\AppTemplates\Tests;

use ESoul\AppTemplates\Command\BuildCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Process\Process;

final class BuildCommandTest extends BuilderTestCase
{
    private function prepare(): void
    {
        $this->put('fake-guidelines', "<?php\n");
        $this->put('layers/a/files/x.txt', 'x');
        $this->put('layers/a/merge/esoul-guidelines.json', '{"layers": ["general"]}');
        $this->variant([['layer' => 'a']]);
    }

    /** @param array<string, string> $options */
    private function runBuild(array $options): CommandTester
    {
        $tester = new CommandTester(new BuildCommand($this->root));
        $tester->execute(['variant' => 'demo', '--guidelines-bin' => 'fake-guidelines', ...$options]);

        return $tester;
    }

    public function testCommitOptionIsRecorded(): void
    {
        $this->prepare();

        $tester = $this->runBuild(['--commit' => 'abc1234']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame(['variant' => 'demo', 'commit' => 'abc1234'], $this->json($this->root . '/build/demo', '.esoul/template.json'));
    }

    public function testCommitMustLookLikeAGitHash(): void
    {
        $this->prepare();

        $tester = $this->runBuild(['--commit' => '1.2.3']);

        self::assertSame(Command::INVALID, $tester->getStatusCode());
        self::assertDirectoryDoesNotExist($this->root . '/build');
    }

    public function testOutsideAGitCheckoutNothingIsRecorded(): void
    {
        $this->prepare();

        $this->runBuild([]);

        self::assertDirectoryDoesNotExist($this->root . '/build/demo/.esoul');
    }

    public function testDefaultCommitIsHeadOfTheSourceRepository(): void
    {
        $this->prepare();
        foreach ([['init', '-q'], ['add', '-A'], ['-c', 'user.name=t', '-c', 'user.email=t@example.com', 'commit', '-q', '-m', 'init']] as $args) {
            $git = new Process(['git', ...$args], $this->root);
            $git->mustRun();
        }
        $head = new Process(['git', 'rev-parse', 'HEAD'], $this->root);
        $head->mustRun();

        $this->runBuild([]);

        self::assertSame(
            ['variant' => 'demo', 'commit' => trim($head->getOutput())],
            $this->json($this->root . '/build/demo', '.esoul/template.json'),
        );
    }
}
