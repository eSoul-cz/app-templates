<?php

declare(strict_types=1);

namespace ESoul\AppTemplates\Tests;

use ESoul\AppTemplates\Builder;
use ESoul\AppTemplates\Catalog;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

abstract class BuilderTestCase extends TestCase
{
    protected string $root;

    protected function setUp(): void {
        $this->root = sys_get_temp_dir() . '/esoul-templates-test-' . bin2hex(random_bytes(4));
        mkdir($this->root, 0777, true);
    }

    protected function tearDown(): void {
        new Filesystem()->remove($this->root);
    }

    protected function put(string $relative, string $content): void {
        new Filesystem()->dumpFile($this->root . '/' . $relative, $content);
    }

    /** @param list<array<string, mixed>> $layers */
    protected function variant(array $layers, string $name = 'demo'): void {
        $this->put('variants/' . $name . '.json', (string) json_encode([
            'name' => $name,
            'package' => 'esoul/template-' . $name,
            'description' => 'Demo variant.',
            'layers' => $layers,
        ]));
    }

    protected function build(string $name = 'demo'): string {
        return new Builder(new Catalog($this->root))->build($name, $this->root . '/build');
    }

    protected function read(string $dir, string $relative): string {
        $content = file_get_contents($dir . '/' . $relative);
        self::assertIsString($content, $relative . ' should exist');

        return $content;
    }

    /** @return array<mixed> decoded JSON object as associative array */
    protected function json(string $dir, string $relative): array {
        $decoded = json_decode($this->read($dir, $relative), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded, $relative . ' should contain a JSON object or list');

        return $decoded;
    }

    /** Value at a path of keys inside a decoded JSON file. */
    protected function jsonAt(string $dir, string $relative, string ...$keys): mixed {
        $value = $this->json($dir, $relative);
        foreach ($keys as $key) {
            self::assertIsArray($value);
            self::assertArrayHasKey($key, $value, $relative . ' should have ' . $key);
            $value = $value[$key];
        }

        return $value;
    }

    /** Like expectExceptionMessage() (deprecated since PHPUnit 13): the message must contain the text. */
    protected function expectExceptionMessageContaining(string $text): void {
        $this->expectExceptionMessageMatches('/' . preg_quote($text, '/') . '/');
    }
}
