<?php

declare(strict_types=1);

namespace App\Domain\Cms;

/** Who wrote a page revision: a person in the admin, an AI assistant or the system (a data migration, a seed). */
enum RevisionSource: string
{
    case Admin = 'admin';
    case Ai = 'ai';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Ai => 'AI assistant',
            self::System => 'System',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Admin => 'gray',
            self::Ai => 'warning',
            self::System => 'info',
        };
    }
}
