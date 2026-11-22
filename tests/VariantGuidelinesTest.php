<?php

declare(strict_types=1);

namespace ESoul\AppTemplates\Tests;

use ESoul\AppTemplates\Builder;
use ESoul\AppTemplates\Catalog;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The guideline layers each real variant declares through the `merge/esoul-guidelines.json` of its layers (guidelines 0.2).
 * A layer that stops declaring its guideline layer, or a variant that gains one, changes the rendered docs: this makes that a decision.
 */
final class VariantGuidelinesTest extends TestCase
{
    private string $out;

    protected function setUp(): void {
        $this->out = sys_get_temp_dir() . '/esoul-templates-guidelines-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void {
        new Filesystem()->remove($this->out);
    }

    /** @return iterable<string, array{string, list<string>}> */
    public static function declaredLayers(): iterable {
        $laravel = ['general', 'framework/laravel', 'package/mutation-testing'];

        yield 'laravel-inertia' => ['laravel-inertia', [...$laravel, 'package/inertia-vue', 'package/laravel-inertia']];
        yield 'laravel-filament' => ['laravel-filament', [...$laravel, 'package/filament']];
        yield 'laravel-inertia-filament' => ['laravel-inertia-filament', [...$laravel, 'package/inertia-vue', 'package/laravel-inertia', 'package/filament']];
        // Lunar owns a Filament panel, which package/filament covers; package/lunar adds the Lunar rules.
        yield 'laravel-lunar' => ['laravel-lunar', [...$laravel, 'package/inertia-vue', 'package/laravel-inertia', 'package/filament', 'package/lunar']];
        yield 'laravel-cms' => ['laravel-cms', [...$laravel, 'package/filament']];
        yield 'lsr-inertia' => ['lsr-inertia', ['general', 'framework/lsr', 'package/inertia-vue', 'package/mutation-testing']];

        foreach (['astro-static', 'astro-cms-frontend', 'astro-cms', 'astro-ssr'] as $astro) {
            yield $astro => [$astro, ['general', 'framework/astro']];
        }
    }

    /** @param list<string> $expected */
    #[DataProvider('declaredLayers')]
    public function test_variant_declares_its_guideline_layers(string $variant, array $expected): void {
        $built = new Builder(new Catalog(dirname(__DIR__)))->build($variant, $this->out);

        $config = json_decode((string) file_get_contents($built . '/esoul-guidelines.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($config);
        $layers = $config['layers'] ?? null;
        self::assertIsArray($layers);

        self::assertSame('general', $layers[0]);
        $sorted = $layers;
        sort($sorted);
        sort($expected);
        self::assertSame($expected, $sorted);
    }
}
