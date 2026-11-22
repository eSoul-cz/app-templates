<?php

declare(strict_types=1);

namespace ESoul\AppTemplates\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The golden export of the CMS contract lives in two layers: the backend test fixture (laravel/cms) and the frontend's own copy
 * (astro/cms-client, `contract/`). A project checks the copy against the backend when both are checked out side by side
 * (`pnpm fixture:check`); here the two layers must agree, so a freshly built pair does as well.
 */
final class ContractCopyTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function files(): iterable {
        yield 'golden export' => ['content-export.golden.json'];
        yield 'media file' => ['pixel.png'];
    }

    #[DataProvider('files')]
    public function test_the_frontend_copy_is_the_backend_fixture(string $file): void {
        $layers = dirname(__DIR__) . '/layers';

        self::assertFileEquals(
            $layers . '/laravel/cms/files/tests/Fixtures/' . $file,
            $layers . '/astro/cms-client/files/contract/' . $file,
            'Copy the backend fixture into astro/cms-client/files/contract/ and rebuild the fixture (`pnpm contract:sync` in a built pair).',
        );
    }
}
