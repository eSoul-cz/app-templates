<?php

declare(strict_types=1);

namespace ESoul\AppTemplates;

use JsonException;
use LogicException;
use stdClass;

/**
 * Deep JSON merge used by the `merge/` directory of a layer.
 *
 * - objects merge recursively, later wins for scalars,
 * - a `null` value in the overlay removes that key from the base object (e.g. drop an upstream dependency),
 * - lists of scalars are unioned, keeping the order of first appearance,
 * - any other list is replaced by the later one,
 * - a value of a different type is replaced by the later one.
 */
final class JsonMerger
{
    /** Merge two decoded values (decode with `decode()` so empty objects survive). */
    public static function merge(mixed $base, mixed $overlay): mixed {
        if ($base instanceof stdClass && $overlay instanceof stdClass) {
            foreach (get_object_vars($overlay) as $key => $value) {
                if ($value === null) {
                    unset($base->{$key});
                    continue;
                }
                $base->{$key} = property_exists($base, (string) $key) ? self::merge($base->{$key}, $value) : $value;
            }

            return $base;
        }

        if (is_array($base) && is_array($overlay) && self::isScalarList($base) && self::isScalarList($overlay)) {
            foreach ($overlay as $value) {
                if ( ! in_array($value, $base, true)) {
                    $base[] = $value;
                }
            }

            return $base;
        }

        return $overlay;
    }

    public static function decode(string $json, string $source): mixed {
        try {
            return json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new TemplateException(sprintf('Invalid JSON in %s: %s', $source, $e->getMessage()), 0, $e);
        }
    }

    /** @param string $indent one indentation unit (the builder always writes 4 spaces; addons keep the unit of the file they change) */
    public static function encode(mixed $value, string $indent = '    '): string {
        $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        if ($indent !== '    ') {
            // Raw newlines cannot occur inside JSON strings, so every `^` below is a real line start.
            $json = preg_replace_callback(
                '/^(?: {4})+/m',
                static fn (array $match): string => str_repeat($indent, intdiv(strlen($match[0]), 4)),
                $json,
            ) ?? throw new LogicException('Re-indenting JSON failed.');
        }

        return $json . "\n";
    }

    /** The indentation unit of a JSON text (one tab or N spaces, from its first indented line); 4 spaces when it has none. */
    public static function detectIndent(string $json): string {
        if (preg_match('/^(\t| +)\S/m', $json, $match) !== 1) {
            return '    ';
        }

        return $match[1][0] === "\t" ? "\t" : $match[1];
    }

    /** @param array<mixed> $list */
    public static function isScalarList(array $list): bool {
        foreach ($list as $item) {
            if ( ! is_scalar($item) && $item !== null) {
                return false;
            }
        }

        return true;
    }
}
