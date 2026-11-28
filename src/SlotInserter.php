<?php

declare(strict_types=1);

namespace ESoul\AppTemplates;

use LogicException;

/**
 * The `insert/` operation of an addon layer. A slot is a line containing `@esoul-slot <slot>`; the addon's block goes immediately before
 * it, wrapped in `@esoul-addon <name>` and `@esoul-addon-end <name>` lines that reuse the comment syntax around the slot.
 */
final class SlotInserter
{
    public const string SLOT_MARKER = '@esoul-slot';

    public const string ADDON_MARKER = '@esoul-addon';

    public const string ADDON_END_MARKER = '@esoul-addon-end';

    public static function isValidSlotName(string $slot): bool {
        return preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*$/D', $slot) === 1;
    }

    /**
     * @param list<string> $lines lines of a file, each with its line ending
     *
     * @return list<int> indexes of the lines that carry the slot
     */
    public static function find(array $lines, string $slot): array {
        $found = [];
        foreach ($lines as $index => $line) {
            if (preg_match(self::slotPattern($slot), $line) === 1) {
                $found[] = $index;
            }
        }

        return $found;
    }

    /** @return list<string> the lines of a file, each with its line ending */
    public static function lines(string $content): array {
        $lines = preg_split('/(?<=\n)/', $content, -1, PREG_SPLIT_NO_EMPTY);

        return $lines === false ? [] : $lines;
    }

    /**
     * Whether the addon's block already sits in the slot: scanning upward from the slot line to the previous slot line (or the file
     * start), a line with `@esoul-addon <name>` exists.
     *
     * @param list<string> $lines
     */
    public static function hasBlock(array $lines, int $slotIndex, string $addon): bool {
        $pattern = '/' . preg_quote(self::ADDON_MARKER, '/') . '[ \t]+' . preg_quote($addon, '/') . '(?![A-Za-z0-9_-])/';

        for ($index = $slotIndex - 1; $index >= 0; $index--) {
            if (str_contains($lines[$index], self::SLOT_MARKER)) {
                return false;
            }
            if (preg_match($pattern, $lines[$index]) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $lines     lines of the target file
     * @param int          $slotIndex index of the slot line (see {@see find()})
     * @param string       $text      the insert file: its lines are indented like the slot line
     *
     * @return string the new file content
     */
    public static function insert(array $lines, int $slotIndex, string $slot, string $addon, string $text, string $eol): string {
        $slotLine = $lines[$slotIndex];
        if (preg_match(self::slotPattern($slot), $slotLine, $match, PREG_OFFSET_CAPTURE) !== 1) {
            throw new LogicException(sprintf('Line %d has no slot "%s".', $slotIndex + 1, $slot));
        }
        [$marker, $at] = $match[0];
        $prefix = substr($slotLine, 0, $at);
        $suffix = rtrim(substr($slotLine, $at + strlen($marker)));
        $indent = preg_match('/^[ \t]*/', $slotLine, $leading) === 1 ? $leading[0] : '';

        $block = [$prefix . self::ADDON_MARKER . ' ' . $addon . $suffix . $eol];
        if (trim($text) !== '') {
            foreach (LineAppend::splitLines(rtrim($text, "\r\n")) as $line) {
                $block[] = (trim($line) === '' ? '' : $indent . $line) . $eol;
            }
        }
        $block[] = $prefix . self::ADDON_END_MARKER . ' ' . $addon . $suffix . $eol;

        array_splice($lines, $slotIndex, 0, $block);

        return implode('', $lines);
    }

    private static function slotPattern(string $slot): string {
        return '/' . preg_quote(self::SLOT_MARKER, '/') . '[ \t]+' . preg_quote($slot, '/') . '(?![A-Za-z0-9_-])/';
    }
}
