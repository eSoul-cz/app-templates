<?php

declare(strict_types=1);

namespace ESoul\AppTemplates;

use JsonException;
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

    public static function encode(mixed $value): string {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    }

    /** @param array<mixed> $list */
    private static function isScalarList(array $list): bool {
        foreach ($list as $item) {
            if ( ! is_scalar($item) && $item !== null) {
                return false;
            }
        }

        return true;
    }
}
