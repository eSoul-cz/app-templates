<?php

declare(strict_types=1);

namespace ESoul\AppTemplates;

/** What applying an addon did to one path, in the order the report lists it. */
enum ChangeKind: string
{
    case Created = 'created';
    case Merged = 'merged';
    case Appended = 'appended';
    case Inserted = 'inserted';
    case Kept = 'kept';
    case Skipped = 'skipped';

    public function heading(): string {
        return match ($this) {
            self::Created => 'Created',
            self::Merged => 'Merged',
            self::Appended => 'Appended',
            self::Inserted => 'Inserted',
            self::Kept => 'Kept (differs)',
            self::Skipped => 'Skipped',
        };
    }
}
