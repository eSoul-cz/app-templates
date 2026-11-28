<?php

declare(strict_types=1);

namespace ESoul\AppTemplates;

/** What applying one addon changed in a project, by path. */
final class AddonReport
{
    /** @var list<array{kind: ChangeKind, path: string, detail: string}> */
    private array $entries = [];

    /** @var array<string, true> paths that were written (created, merged, appended or inserted into) */
    private array $written = [];

    /** @var list<string> Composer packages the merges added to `require` and `require-dev` of the root `composer.json` */
    private array $addedPackages = [];

    public function add(ChangeKind $kind, string $path, string $detail = ''): void {
        $this->entries[] = ['kind' => $kind, 'path' => $path, 'detail' => $detail];

        if ($kind !== ChangeKind::Kept && $kind !== ChangeKind::Skipped) {
            $this->written[$path] = true;
        }
    }

    /** @param list<string> $packages */
    public function addPackages(array $packages): void {
        $this->addedPackages = array_values(array_unique([...$this->addedPackages, ...$packages]));
    }

    /** @return list<array{kind: ChangeKind, path: string, detail: string}> */
    public function entries(ChangeKind $kind): array {
        return array_values(array_filter($this->entries, static fn (array $entry): bool => $entry['kind'] === $kind));
    }

    public function wrote(string $path): bool {
        return isset($this->written[$path]);
    }

    /** @return list<string> */
    public function addedPackages(): array {
        return $this->addedPackages;
    }

    public function changedAnything(): bool {
        return $this->written !== [];
    }
}
