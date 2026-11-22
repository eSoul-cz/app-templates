<?php

declare(strict_types=1);

namespace ESoul\AppTemplates\Tests;

use ESoul\AppTemplates\Builder;
use ESoul\AppTemplates\Catalog;
use ESoul\AppTemplates\GuidelinesRenderer;
use ESoul\AppTemplates\TemplateException;

final class GuidelinesRendererTest extends BuilderTestCase
{
    public function test_build_runs_the_installer_in_the_variant_root_after_layering(): void {
        // A stand-in installer that records its arguments and the config it found, as `sync` would read it.
        $this->put('fake-guidelines', <<<'PHP'
            <?php
            file_put_contents('rendered.txt', implode(' ', array_slice($argv, 1)) . "\n" . file_get_contents('esoul-guidelines.json'));
            PHP);
        $this->put('layers/a/merge/esoul-guidelines.json', '{"layers": ["general"]}');
        $this->variant([['layer' => 'a']]);

        $out = $this->buildWith($this->root . '/fake-guidelines');

        [$arguments, $config] = explode("\n", $this->read($out, 'rendered.txt'), 2);
        self::assertSame('sync --no-interaction', $arguments);
        $decoded = json_decode($config, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertSame(['general'], $decoded['layers']);
    }

    public function test_failing_installer_fails_the_build_with_its_output(): void {
        $this->put('fake-guidelines', "<?php\nfwrite(STDERR, 'Refusing: drift');\nexit(1);\n");
        $this->put('layers/a/merge/esoul-guidelines.json', '{"layers": ["general"]}');
        $this->variant([['layer' => 'a']]);

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageContaining('Refusing: drift');

        $this->buildWith($this->root . '/fake-guidelines');
    }

    public function test_missing_installer_fails_the_build(): void {
        $this->put('layers/a/merge/esoul-guidelines.json', '{"layers": ["general"]}');
        $this->variant([['layer' => 'a']]);

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageContaining('The guidelines installer was not found');

        $this->buildWith($this->root . '/missing');
    }

    private function buildWith(string $binary): string {
        return new Builder(new Catalog($this->root), new GuidelinesRenderer($binary))->build('demo', $this->root . '/build');
    }
}
