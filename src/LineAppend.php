<?php

declare(strict_types=1);

namespace ESoul\AppTemplates;

/** The `append/` operation of a layer: add the lines of a file that the target does not have yet. */
final class LineAppend
{
    /**
     * A non-blank line already present in `$existing` is skipped; blank lines keep section spacing but are never stacked, and the
     * result neither starts nor ends with a blank line.
     *
     * @param int $added set to the number of non-blank lines that were appended
     */
    public static function append(string $existing, string $source, int &$added = 0): string {
        $added = 0;
        $lines = $existing === '' ? [] : self::splitLines(rtrim($existing, "\r\n"));
        $present = array_fill_keys(array_map(rtrim(...), $lines), true);

        foreach (self::splitLines(rtrim($source, "\r\n")) as $line) {
            $line = rtrim($line);

            if ($line === '') {
                // Keep section spacing but never stack blank lines or start a file with one.
                if ($lines !== [] && end($lines) !== '') {
                    $lines[] = '';
                }
                continue;
            }
            if (isset($present[$line])) {
                continue;
            }

            $lines[] = $line;
            $present[$line] = true;
            $added++;
        }

        while ($lines !== [] && end($lines) === '') {
            array_pop($lines);
        }

        return $lines === [] ? '' : implode("\n", $lines) . "\n";
    }

    /** @return list<string> */
    public static function splitLines(string $text): array {
        $lines = preg_split('/\R/', $text);

        return $lines === false ? [] : $lines;
    }
}
