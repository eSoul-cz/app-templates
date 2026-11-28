<?php

declare(strict_types=1);

namespace ESoul\AppTemplates;

use Symfony\Component\Process\Process;

/** The git checkout this builder runs from: the source commit that built variants record. */
final readonly class SourceRepository
{
    public function __construct(private string $root) {
    }

    public static function isCommitHash(string $value): bool {
        return preg_match('/^[0-9a-f]{7,40}$/', $value) === 1;
    }

    /** HEAD of the source repository, or null when `root` is not the top level of a git checkout (a parent repository must not leak in). */
    public function headCommit(): ?string {
        $top = new Process(['git', 'rev-parse', '--show-toplevel'], $this->root);
        $top->run();
        if ( ! $top->isSuccessful() || realpath(trim($top->getOutput())) !== realpath($this->root)) {
            return null;
        }

        $head = new Process(['git', 'rev-parse', 'HEAD'], $this->root);
        $head->run();

        return $head->isSuccessful() ? trim($head->getOutput()) : null;
    }

    /** First line of the commit message, or null when the commit is unknown here. */
    public function subject(string $commit): ?string {
        $log = new Process(['git', 'log', '-1', '--format=%s', $commit], $this->root);
        $log->run();
        $subject = trim($log->getOutput());

        return $log->isSuccessful() && $subject !== '' ? $subject : null;
    }

    /** Whether the commit exists in this checkout. */
    public function hasCommit(string $commit): bool {
        $check = new Process(['git', 'cat-file', '-e', $commit . '^{commit}'], $this->root);
        $check->run();

        return $check->isSuccessful();
    }

    /** Whether `CHANGELOG.md` has a commit after `$commit` (up to HEAD). */
    public function changelogChangedSince(string $commit): bool {
        $log = new Process(['git', 'log', '--format=%H', $commit . '..HEAD', '--', 'CHANGELOG.md'], $this->root);
        $log->run();

        return $log->isSuccessful() && trim($log->getOutput()) !== '';
    }
}
