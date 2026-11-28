<?php

declare(strict_types=1);

namespace ESoul\AppTemplates\Tests;

use ESoul\AppTemplates\AddonLoader;
use ESoul\AppTemplates\Catalog;
use ESoul\AppTemplates\TemplateException;

final class AddonLoaderTest extends AddonTestCase
{
    private function load(string $reference): void {
        new AddonLoader(new Catalog($this->root))->load($reference);
    }

    public function test_reads_an_addon_of_the_repository(): void {
        $this->manifest('pulse', ['demo' => ['addons/pulse/core']], ['steps' => ['php artisan migrate']]);
        $this->put('layers/addons/pulse/core/files/a.txt', 'a');

        $addon = new AddonLoader(new Catalog($this->root))->load('pulse');

        self::assertSame('pulse', $addon->name);
        self::assertSame(['demo' => ['addons/pulse/core']], $addon->variants);
        self::assertSame(['php artisan migrate'], $addon->steps);
        self::assertFalse($addon->isExternal());
        self::assertNull($addon->source);
    }

    public function test_unknown_manifest_key_is_an_error(): void {
        $this->manifest('pulse', extra: ['requires' => ['x']]);
        $this->put('layers/addons/pulse/core/files/a.txt', 'a');

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageContaining('unknown key "requires"');
        $this->load('pulse');
    }

    public function test_name_must_equal_the_file_name(): void {
        $this->put('addons/pulse.json', (string) json_encode(['name' => 'other', 'description' => 'x', 'variants' => ['demo' => ['a']]]));

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageContaining('"name" is "other" but the file is called "pulse.json"');
        $this->load('pulse');
    }

    public function test_every_listed_layer_must_exist(): void {
        $this->manifest('pulse', ['demo' => ['addons/pulse/missing']]);

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageContaining('unknown layer "addons/pulse/missing"');
        $this->load('pulse');
    }

    public function test_every_listed_variant_must_exist(): void {
        $this->manifest('pulse', ['nope' => ['addons/pulse/core']]);
        $this->put('layers/addons/pulse/core/files/a.txt', 'a');

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageContaining('unknown variant "nope"');
        $this->load('pulse');
    }

    public function test_variants_must_be_a_non_empty_map_of_non_empty_lists(): void {
        $this->manifest('pulse', []);

        try {
            $this->load('pulse');
            self::fail('An addon without variants must be refused.');
        } catch (TemplateException $e) {
            self::assertStringContainsString('"variants" must be a non-empty object', $e->getMessage());
        }

        $this->manifest('pulse', ['demo' => []]);
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageContaining('"variants.demo" must be a non-empty list');
        $this->load('pulse');
    }

    public function test_null_in_a_merge_file_is_an_error(): void {
        $this->manifest('pulse');
        $this->put('layers/addons/pulse/core/merge/composer.json', '{"require": {"laravel/pao": null}}');

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageContaining('"require.laravel/pao" is null');
        $this->load('pulse');
    }

    public function test_delete_txt_is_an_error(): void {
        $this->manifest('pulse');
        $this->put('layers/addons/pulse/core/delete.txt', "drop.txt\n");

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageContaining('delete.txt is not allowed');
        $this->load('pulse');
    }

    public function test_merge_only_takes_json(): void {
        $this->manifest('pulse');
        $this->put('layers/addons/pulse/core/merge/notes.txt', 'x');

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageContaining('only .json files can be merged');
        $this->load('pulse');
    }

    public function test_insert_needs_a_slot_directory(): void {
        $this->manifest('pulse');
        $this->put('layers/addons/pulse/core/insert/routes.php', 'x');

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageContaining('insert/<slot>/<path>');
        $this->load('pulse');
    }

    public function test_addon_placeholder_is_an_error_in_an_addon_of_the_repository(): void {
        $this->manifest('pulse');
        $this->put('layers/addons/pulse/core/merge/esoul-guidelines.json', '{"sources": ["@addon"]}');

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageContaining('"@addon", which only an external addon may use');
        $this->load('pulse');
    }

    public function test_external_addon_names_only_its_own_layers(): void {
        $this->put('ext/addon.json', (string) json_encode(['name' => 'acme', 'description' => 'x', 'variants' => ['demo' => ['base']]]));
        $this->put('ext/layers/own/files/a.txt', 'a');

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageContaining('unknown layer "base"');
        $this->load($this->root . '/ext');
    }

    public function test_directory_without_a_manifest_is_refused(): void {
        $this->put('ext/layers/own/files/a.txt', 'a');

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageContaining('has no addon.json');
        $this->load($this->root . '/ext');
    }

    public function test_unknown_reference_names_the_available_addons(): void {
        $this->manifest('pulse');
        $this->put('layers/addons/pulse/core/files/a.txt', 'a');

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageContaining('available: pulse');
        $this->load($this->root . '/does-not-exist.git');
    }
}
