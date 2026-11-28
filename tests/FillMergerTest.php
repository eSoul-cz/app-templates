<?php

declare(strict_types=1);

namespace ESoul\AppTemplates\Tests;

use ESoul\AppTemplates\FillMerger;
use ESoul\AppTemplates\JsonMerger;
use ESoul\AppTemplates\SlotInserter;
use ESoul\AppTemplates\TemplateException;
use PHPUnit\Framework\TestCase;

final class FillMergerTest extends TestCase
{
    public function test_fills_and_unions_without_overwriting(): void {
        $base = JsonMerger::decode('{"a": 1, "list": ["x"], "o": {"k": "keep"}, "objs": [{"i": 1}]}', 'base');
        $overlay = JsonMerger::decode('{"a": 2, "b": 3, "list": ["x", "y"], "o": {"k": "other", "n": true}, "objs": [{"i": 2}]}', 'overlay');

        $result = FillMerger::merge($base, $overlay);

        self::assertSame(
            '{"a":1,"list":["x","y"],"o":{"k":"keep","n":true},"objs":[{"i":1}],"b":3}',
            json_encode($result->value, JSON_THROW_ON_ERROR),
        );
        self::assertSame(['a', 'o.k', 'objs'], $result->kept);
        self::assertTrue($result->changed());
    }

    public function test_identical_overlay_changes_nothing_and_keeps_nothing(): void {
        $json = '{"a": 1, "list": ["x"], "o": {"k": {"deep": [1]}}}';

        $result = FillMerger::merge(JsonMerger::decode($json, 'a'), JsonMerger::decode($json, 'b'));

        self::assertFalse($result->changed());
        self::assertSame([], $result->kept);
    }

    public function test_null_is_found_anywhere_in_an_overlay(): void {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageMatches('/"a\.b\.\[1\]" is null/');
        FillMerger::assertNoNull(JsonMerger::decode('{"a": {"b": [1, null]}}', 'x'), 'x');
    }

    public function test_placeholder_is_replaced_only_as_a_whole_value(): void {
        $value = JsonMerger::decode('{"url": "@addon", "text": "see @addon", "list": ["@addon"], "n": {"u": "@addon"}}', 'x');

        $substituted = FillMerger::substitute($value, '@addon', 'https://example.com/r.git');

        self::assertSame(
            '{"url":"https://example.com/r.git","text":"see @addon","list":["https://example.com/r.git"],"n":{"u":"https://example.com/r.git"}}',
            json_encode($substituted, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        );
    }

    public function test_indentation_is_detected_and_reapplied(): void {
        self::assertSame('  ', JsonMerger::detectIndent("{\n  \"a\": 1\n}\n"));
        self::assertSame("\t", JsonMerger::detectIndent("{\n\t\"a\": 1\n}\n"));
        self::assertSame('    ', JsonMerger::detectIndent('{}'));
        self::assertSame("{\n  \"a\": {\n    \"b\": []\n  }\n}\n", JsonMerger::encode(['a' => ['b' => []]], '  '));
    }

    public function test_slot_inserter_keeps_windows_line_endings(): void {
        $lines = SlotInserter::lines("a\r\n  # @esoul-slot x\r\n");

        $result = SlotInserter::insert($lines, 1, 'x', 'pulse', "one\ntwo\n", "\r\n");

        self::assertSame("a\r\n  # @esoul-addon pulse\r\n  one\r\n  two\r\n  # @esoul-addon-end pulse\r\n  # @esoul-slot x\r\n", $result);
    }
}
