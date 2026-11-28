<?php

declare(strict_types=1);

namespace ESoul\AppTemplates;

use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * A project directory with pending writes. An addon is planned against it, so every error of the addon surfaces before the first byte
 * is written; {@see flush()} then writes what actually changed.
 */
final class AddonWorkspace
{
    /** @var array<string, array{content: string, executable: int}> relative path => pending content */
    private array $pending = [];

    private readonly Filesystem $fs;

    public function __construct(private readonly string $root) {
        $this->fs = new Filesystem();
    }

    /** Whether the file exists on disk, ignoring pending writes. */
    public function onDisk(string $relative): bool {
        return is_file($this->path($relative));
    }

    public function exists(string $relative): bool {
        return isset($this->pending[$relative]) || is_file($this->path($relative));
    }

    public function read(string $relative): string {
        if (isset($this->pending[$relative])) {
            return $this->pending[$relative]['content'];
        }

        $content = is_file($this->path($relative)) ? file_get_contents($this->path($relative)) : false;
        if ($content === false) {
            throw new TemplateException(sprintf('Cannot read %s in %s.', $relative, $this->root));
        }

        return $content;
    }

    /** @param int $executable executable permission bits (`0111` or 0) a new file gets */
    public function write(string $relative, string $content, int $executable = 0): void {
        $path = $this->path($relative);
        if (is_dir($path)) {
            throw new TemplateException(sprintf('%s is a directory in %s, an addon cannot write a file there.', $relative, $this->root));
        }

        $this->pending[$relative] = ['content' => $content, 'executable' => $executable];
    }

    /**
     * Write every pending file whose content differs from disk. An existing file keeps its permissions.
     *
     * @return list<string> relative paths that were written
     */
    public function flush(): array {
        $written = [];

        foreach ($this->pending as $relative => $file) {
            $path = $this->path($relative);
            $exists = is_file($path);
            if ($exists && file_get_contents($path) === $file['content']) {
                continue;
            }

            try {
                $this->fs->dumpFile($path, $file['content']);
                if ( ! $exists && $file['executable'] !== 0) {
                    $this->fs->chmod($path, (fileperms($path) & 0777) | $file['executable']);
                }
            } catch (IOException $e) {
                throw new TemplateException(sprintf(
                    'Could not write %s: %s. Already written: %s.',
                    $relative,
                    $e->getMessage(),
                    $written === [] ? 'nothing' : implode(', ', $written),
                ), 0, $e);
            }
            $written[] = $relative;
        }
        $this->pending = [];

        return $written;
    }

    private function path(string $relative): string {
        return $this->root . '/' . $relative;
    }
}
