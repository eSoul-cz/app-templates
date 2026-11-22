<?php

declare(strict_types=1);

namespace ESoul\AppTemplates\Tests;

use ESoul\AppTemplates\Glob;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GlobTest extends TestCase
{
    /** @return iterable<string, array{string, string, bool}> */
    public static function cases(): iterable {
        yield 'literal' => ['Jenkinsfile', 'Jenkinsfile', true];
        yield 'literal is anchored' => ['Jenkinsfile', 'sub/Jenkinsfile', false];
        yield 'star stays in directory' => ['*.json', 'a/b.json', false];
        yield 'star matches dotfile' => ['*.yaml', '.coderabbit.yaml', true];
        yield 'double star tail' => ['.github/**', '.github/workflows/ci.yml', true];
        yield 'double star slash matches zero dirs' => ['resources/**/*.css', 'resources/app.css', true];
        yield 'double star slash matches many dirs' => ['resources/**/*.css', 'resources/a/b/app.css', true];
        yield 'question mark' => ['a?c', 'abc', true];
        yield 'question mark not slash' => ['a?c', 'a/c', false];
        yield 'regex characters are literal' => ['a.b', 'axb', false];
    }

    #[DataProvider('cases')]
    public function test_matches(string $pattern, string $path, bool $expected): void {
        self::assertSame($expected, Glob::matches($pattern, $path));
    }
}
