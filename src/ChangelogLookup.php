<?php

declare(strict_types=1);

namespace ESoul\AppTemplates;

/** Finds the changelog entry that introduces a slot, so a refusal can tell which update the project needs. */
final readonly class ChangelogLookup
{
    public function __construct(private string $changelog) {
    }

    /**
     * Heading title (without the date) of the oldest entry whose body mentions ``slot `<slot>` ``, or null.
     * The oldest one, because that is the entry that introduced the slot; later entries only use it.
     */
    public function entryForSlot(string $slot): ?string {
        if ( ! is_file($this->changelog)) {
            return null;
        }

        $needle = 'slot `' . $slot . '`';
        $title = null;
        $mentions = false;
        $found = null;

        foreach (LineAppend::splitLines((string) file_get_contents($this->changelog)) as $line) {
            if (preg_match('/^#{2,3} (?!Unreleased\b)(.+)$/', $line, $heading) === 1) {
                $found = $mentions && $title !== null ? $title : $found;
                $title = preg_replace('/^\d{4}-\d{2}-\d{2}: /', '', trim($heading[1]));
                $mentions = false;
                continue;
            }
            $mentions = $mentions || str_contains($line, $needle);
        }

        return $mentions && $title !== null ? $title : $found;
    }
}
