<?php

declare(strict_types=1);

namespace App\Domain\AiConnector;

/** The OAuth scopes of the AI connector. A tool declares the one it needs; the consent screen explains each in plain words. */
enum ConnectorScope: string
{
    case ContentRead = 'content:read';
    case ContentWrite = 'content:write';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $scope): string => $scope->value, self::cases());
    }
}
