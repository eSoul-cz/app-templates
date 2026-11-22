<?php

declare(strict_types=1);

namespace ESoul\AppTemplates;

use stdClass;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Syncs a built variant into a branch of its repository as one commit.
 *
 * The commit's tree is exactly the build (nothing of the remote tree survives), its parent is the current tip of the branch (none when
 * the branch does not exist yet), and nothing is pushed when the tree equals the tip's. Only git plumbing runs, against a throwaway
 * git directory with the build as work tree, so the build directory is never touched and needs no repository of its own.
 *
 * Credentials: with `TEMPLATES_PUBLISH_TOKEN` set (a GitHub App installation token or a personal access token), git gets it through a
 * credential helper that reads the variable (it never appears in a command line) and signs in as `x-access-token`; without it git uses
 * whatever authentication the machine has.
 */
final readonly class Publisher
{
    /** Environment variable that holds the token git authenticates with. */
    public const string TOKEN_ENV = 'TEMPLATES_PUBLISH_TOKEN';

    private const string AUTHOR_NAME = 'eSoul app-templates publisher';

    private const string AUTHOR_EMAIL = 'noreply@esoul.cz';

    private Filesystem $fs;

    public function __construct() {
        $this->fs = new Filesystem();
    }

    /**
     * @param string $builtDir   a pristine `Builder::build()` result with `.esoul/template.json`
     * @param string $repository URL or path of the target repository
     * @param string $message    commit subject; the source commit is appended as the body
     */
    public function publish(string $builtDir, string $repository, string $branch, string $message, bool $dryRun = false): PublishResult {
        if ($repository === '' || str_starts_with($repository, '-')) {
            throw new TemplateException(sprintf('Invalid repository "%s".', $repository));
        }
        if (preg_match('#^[A-Za-z0-9][A-Za-z0-9._/-]*$#D', $branch) !== 1) {
            throw new TemplateException(sprintf('Invalid branch "%s".', $branch));
        }
        $source = $this->sourceCommit($builtDir);

        $gitDir = sys_get_temp_dir() . '/esoul-publish-' . bin2hex(random_bytes(6));
        try {
            $this->run(['git', 'init', '--quiet', '--bare', $gitDir], 'git init');

            $parent = $this->fetchTip($gitDir, $repository, $branch);
            $parentTree = $parent === null ? null : $this->git($gitDir, ['rev-parse', $parent . '^{tree}']);

            // --force: the published tree is the build, also where the variant's own .gitignore would drop a file.
            $this->git($gitDir, ['add', '--all', '--force', '.'], $builtDir);
            $tree = $this->git($gitDir, ['write-tree']);
            $files = count(array_filter(explode("\n", $this->git($gitDir, ['ls-files'])), static fn (string $line): bool => $line !== ''));

            if ($tree === $parentTree) {
                return new PublishResult(PublishResult::UNCHANGED, null, $files, false);
            }

            $arguments = ['commit-tree', $tree];
            if ($parent !== null) {
                array_push($arguments, '-p', $parent);
            }
            array_push($arguments, '-m', rtrim($message) . "\n\nBuilt from app-templates commit " . $source . '.');
            $commit = $this->git($gitDir, $arguments, null, ['user.name=' . self::AUTHOR_NAME, 'user.email=' . self::AUTHOR_EMAIL]);

            if ( ! $dryRun) {
                $this->git($gitDir, ['push', '--quiet', $repository, $commit . ':refs/heads/' . $branch]);
            }

            return new PublishResult($parent === null ? PublishResult::CREATED : PublishResult::UPDATED, $commit, $files, ! $dryRun);
        } finally {
            $this->fs->remove($gitDir);
        }
    }

    /** The source commit recorded by the build; a build without one cannot be published (the update flow needs it). */
    private function sourceCommit(string $builtDir): string {
        $file = $builtDir . '/.esoul/template.json';
        if ( ! is_file($file)) {
            throw new TemplateException(sprintf('%s has no .esoul/template.json: build with a source commit before publishing.', $builtDir));
        }

        $data = JsonMerger::decode((string) file_get_contents($file), $file);
        $commit = $data instanceof stdClass ? ($data->commit ?? null) : null;
        if ( ! is_string($commit) || ! SourceRepository::isCommitHash($commit)) {
            throw new TemplateException(sprintf('%s does not record a source commit.', $file));
        }

        return $commit;
    }

    /** Fetches the tip of the branch (depth 1) and returns its hash; null when the repository has no such branch. */
    private function fetchTip(string $gitDir, string $repository, string $branch): ?string {
        $ref = 'refs/heads/' . $branch;
        if (trim($this->git($gitDir, ['ls-remote', '--heads', $repository, $ref])) === '') {
            return null;
        }

        $this->git($gitDir, ['fetch', '--quiet', '--depth=1', $repository, $ref]);

        return $this->git($gitDir, ['rev-parse', 'FETCH_HEAD']);
    }

    /**
     * Runs git against the throwaway git directory (the build is the work tree when `$workTree` is given) and returns its trimmed output.
     *
     * @param list<string> $arguments git subcommand and its arguments
     * @param list<string> $config    `key=value` settings for this call (`git -c`)
     */
    private function git(string $gitDir, array $arguments, ?string $workTree = null, array $config = []): string {
        if (self::envValue(self::TOKEN_ENV) !== null) {
            // The first setting clears helpers configured on the machine; the second reads the token inside the helper's shell.
            array_push($config, 'credential.helper=', sprintf('credential.helper=!f() { echo username=x-access-token; echo "password=${%s}"; }; f', self::TOKEN_ENV));
        }

        $command = ['git'];
        foreach ($config as $setting) {
            array_push($command, '-c', $setting);
        }
        $command[] = '--git-dir=' . $gitDir;
        if ($workTree !== null) {
            $command[] = '--work-tree=' . $workTree;
        }

        return $this->run([...$command, ...$arguments], 'git ' . $arguments[0], $workTree);
    }

    /** @param list<string> $command */
    private function run(array $command, string $what, ?string $cwd = null): string {
        $process = new Process($command, $cwd, ['GIT_TERMINAL_PROMPT' => '0']);
        $process->setTimeout(300);
        $process->run();

        if ( ! $process->isSuccessful()) {
            throw new TemplateException(sprintf(
                '%s failed (exit %d):%s%s',
                $what,
                (int) $process->getExitCode(),
                PHP_EOL,
                trim($process->getErrorOutput() . PHP_EOL . $process->getOutput()),
            ));
        }

        return trim($process->getOutput());
    }

    private static function envValue(string $name): ?string {
        $value = getenv($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
