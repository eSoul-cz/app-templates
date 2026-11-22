<?php

declare(strict_types=1);

namespace ESoul\AppTemplates\Tests;

use ESoul\AppTemplates\Builder;
use ESoul\AppTemplates\Catalog;
use ESoul\AppTemplates\Publisher;
use ESoul\AppTemplates\PublishResult;
use ESoul\AppTemplates\TemplateException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

final class PublisherTest extends BuilderTestCase
{
    private const string SOURCE = 'abc1234';

    private string $remote;

    protected function setUp(): void {
        parent::setUp();
        $this->remote = $this->root . '/remote.git';
        new Process(['git', 'init', '--quiet', '--bare', $this->remote])->mustRun();
        $this->put('layers/a/files/README.md', "# Demo\n");
        $this->put('layers/a/files/src/old.txt', 'old');
        $this->put('layers/a/files/.gitignore', "/ignored.txt\n");
        $this->put('layers/a/files/ignored.txt', 'kept although ignored');
        $this->variant([['layer' => 'a']]);
    }

    private function built(?string $source = self::SOURCE): string {
        return new Builder(new Catalog($this->root))->build('demo', $this->root . '/build', $source);
    }

    /** @param list<string> $arguments */
    private function remoteGit(array $arguments): string {
        $git = new Process(['git', '--git-dir=' . $this->remote, ...$arguments]);
        $git->mustRun();

        return trim($git->getOutput());
    }

    private function publish(string $built, bool $dryRun = false): PublishResult {
        return new Publisher()->publish($built, $this->remote, 'main', ':sparkles: Add a thing', $dryRun);
    }

    public function test_it_creates_the_branch_in_an_empty_repository(): void {
        $result = $this->publish($this->built());

        self::assertSame(PublishResult::CREATED, $result->status);
        self::assertTrue($result->pushed);
        self::assertSame($result->commit, $this->remoteGit(['rev-parse', 'main']));
        self::assertSame('1', $this->remoteGit(['rev-list', '--count', 'main']));
        self::assertSame("# Demo", $this->remoteGit(['show', 'main:README.md']));
        self::assertSame('kept although ignored', $this->remoteGit(['show', 'main:ignored.txt']));
        self::assertStringContainsString(self::SOURCE, $this->remoteGit(['show', 'main:.esoul/template.json']));

        $message = $this->remoteGit(['log', '-1', '--format=%B', 'main']);
        self::assertStringStartsWith(':sparkles: Add a thing', $message);
        self::assertStringContainsString('Built from app-templates commit ' . self::SOURCE, $message);
    }

    public function test_a_second_publish_of_the_same_build_changes_nothing(): void {
        $first = $this->publish($this->built());

        $second = $this->publish($this->built());

        self::assertSame(PublishResult::UNCHANGED, $second->status);
        self::assertNull($second->commit);
        self::assertFalse($second->pushed);
        self::assertSame($first->commit, $this->remoteGit(['rev-parse', 'main']));
    }

    public function test_a_changed_build_becomes_one_commit_on_top_and_replaces_the_whole_tree(): void {
        $first = $this->publish($this->built());
        $this->put('layers/a/files/README.md', "# Demo 2\n");
        new Filesystem()->remove($this->root . '/layers/a/files/src/old.txt');

        $second = $this->publish($this->built('def5678'));

        self::assertSame(PublishResult::UPDATED, $second->status);
        self::assertNotSame($first->commit, $second->commit);
        self::assertSame('2', $this->remoteGit(['rev-list', '--count', 'main']));
        self::assertSame($first->commit, $this->remoteGit(['rev-parse', 'main~1']));
        self::assertSame('# Demo 2', $this->remoteGit(['show', 'main:README.md']));
        self::assertStringNotContainsString('src/old.txt', $this->remoteGit(['ls-tree', '-r', '--name-only', 'main']));
    }

    public function test_a_dry_run_pushes_nothing(): void {
        $result = $this->publish($this->built(), true);

        self::assertSame(PublishResult::CREATED, $result->status);
        self::assertNotNull($result->commit);
        self::assertFalse($result->pushed);
        self::assertSame('', $this->remoteGit(['for-each-ref', 'refs/heads']));
    }

    public function test_a_build_without_a_source_commit_cannot_be_published(): void {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageContaining('no .esoul/template.json');

        $this->publish($this->built(null));
    }

    public function test_an_unreachable_repository_fails_with_the_git_message(): void {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageContaining('git ls-remote failed');

        new Publisher()->publish($this->built(), $this->root . '/missing.git', 'main', 'msg');
    }

    public function test_the_build_directory_is_left_untouched(): void {
        $built = $this->built();
        $before = $this->filesOf($built);

        $this->publish($built);

        self::assertSame($before, $this->filesOf($built));
        self::assertDirectoryDoesNotExist($built . '/.git');
    }

    private function filesOf(string $dir): string {
        $find = new Process(['find', '.', '-type', 'f'], $dir);
        $find->mustRun();
        $lines = explode("\n", trim($find->getOutput()));
        sort($lines);

        return implode("\n", $lines);
    }
}
