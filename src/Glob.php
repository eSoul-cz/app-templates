<?php

declare(strict_types=1);

namespace ESoul\AppTemplates;

/**
 * Minimal glob matcher for slash separated relative paths.
 *
 * `*` and `?` never cross a `/`, `**` does, and `**` followed by `/` also matches zero directories.
 * Dotfiles are matched like any other file. No brace expansion.
 */
final class Glob
{
    /** @var array<string, string> */
    private static array $cache = [];

    public static function matches(string $pattern, string $path): bool {
        $regex = self::$cache[$pattern] ??= self::toRegex($pattern);

        return preg_match($regex, $path) === 1;
    }

    /** @param list<string> $patterns */
    public static function matchesAny(array $patterns, string $path): bool {
        foreach ($patterns as $pattern) {
            if (self::matches($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    private static function toRegex(string $pattern): string {
        $pattern = trim($pattern, '/');
        $regex = '';
        $length = strlen($pattern);

        for ($i = 0; $i < $length; $i++) {
            $char = $pattern[$i];

            if ($char === '*') {
                if (($pattern[$i + 1] ?? '') === '*') {
                    $i++;
                    if (($pattern[$i + 1] ?? '') === '/') {
                        $i++;
                        $regex .= '(?:.*/)?';
                    } else {
                        $regex .= '.*';
                    }
                } else {
                    $regex .= '[^/]*';
                }
            } elseif ($char === '?') {
                $regex .= '[^/]';
            } else {
                $regex .= preg_quote($char, '#');
            }
        }

        return '#^' . $regex . '$#s';
    }
}
