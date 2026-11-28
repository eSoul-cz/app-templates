<?php

declare(strict_types=1);

namespace ESoul\AppTemplates;

use stdClass;

/**
 * The `merge/` operation of an addon layer: fill only. Missing keys are added recursively, lists of scalars are unioned (first-seen
 * order, new values appended), and an existing value is never overwritten: when it differs from the overlay it is kept and reported by
 * its JSON path. Decode with {@see JsonMerger::decode()} so empty objects survive.
 */
final class FillMerger
{
    public static function merge(mixed $base, mixed $overlay): FillMergeResult {
        $added = [];
        $kept = [];
        $value = self::fill($base, $overlay, [], $added, $kept);

        return new FillMergeResult($value, $added, $kept);
    }

    /** `null` (deletion) anywhere in an addon's overlay is an error. */
    public static function assertNoNull(mixed $overlay, string $source): void {
        self::walk($overlay, [], static function (mixed $value, array $path) use ($source): void {
            if ($value === null) {
                throw new TemplateException(sprintf('%s: "%s" is null; an addon cannot delete, merge/ only fills.', $source, self::label($path)));
            }
        });
    }

    /** Replace every string value that is exactly `$placeholder` with `$replacement`. */
    public static function substitute(mixed $value, string $placeholder, string $replacement): mixed {
        if ($value === $placeholder) {
            return $replacement;
        }
        if ($value instanceof stdClass) {
            foreach (get_object_vars($value) as $key => $child) {
                $value->{(string) $key} = self::substitute($child, $placeholder, $replacement);
            }

            return $value;
        }
        if (is_array($value)) {
            return array_map(static fn (mixed $item): mixed => self::substitute($item, $placeholder, $replacement), $value);
        }

        return $value;
    }

    /** Whether a string value anywhere in `$value` is exactly `$needle`. */
    public static function contains(mixed $value, string $needle): bool {
        $found = false;
        self::walk($value, [], static function (mixed $item) use ($needle, &$found): void {
            $found = $found || $item === $needle;
        });

        return $found;
    }

    /** @param list<string> $path */
    public static function label(array $path): string {
        return $path === [] ? '$' : implode('.', $path);
    }

    /**
     * @param list<string>                                  $path
     * @param list<array{path: list<string>, value: mixed}> $added
     * @param list<string>                                  $kept
     */
    private static function fill(mixed $base, mixed $overlay, array $path, array &$added, array &$kept): mixed {
        if ($base instanceof stdClass && $overlay instanceof stdClass) {
            foreach (get_object_vars($overlay) as $key => $value) {
                $key = (string) $key;
                if (property_exists($base, $key)) {
                    $base->{$key} = self::fill($base->{$key}, $value, [...$path, $key], $added, $kept);
                    continue;
                }

                $base->{$key} = $value;
                $added[] = ['path' => [...$path, $key], 'value' => $value];
            }

            return $base;
        }

        if (is_array($base) && is_array($overlay) && JsonMerger::isScalarList($base) && JsonMerger::isScalarList($overlay)) {
            foreach ($overlay as $item) {
                if ( ! in_array($item, $base, true)) {
                    $base[] = $item;
                    $added[] = ['path' => [...$path, '[]'], 'value' => $item];
                }
            }

            return $base;
        }

        if (json_encode($base, JSON_THROW_ON_ERROR) !== json_encode($overlay, JSON_THROW_ON_ERROR)) {
            $kept[] = self::label($path);
        }

        return $base;
    }

    /**
     * @param list<string>                         $path
     * @param callable(mixed, list<string>): void $visit
     */
    private static function walk(mixed $value, array $path, callable $visit): void {
        $visit($value, $path);

        if ($value instanceof stdClass) {
            foreach (get_object_vars($value) as $key => $child) {
                self::walk($child, [...$path, (string) $key], $visit);
            }
        } elseif (is_array($value)) {
            foreach ($value as $index => $child) {
                self::walk($child, [...$path, '[' . $index . ']'], $visit);
            }
        }
    }
}
