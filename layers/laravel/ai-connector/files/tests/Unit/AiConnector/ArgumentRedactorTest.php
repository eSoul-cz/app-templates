<?php

declare(strict_types=1);

namespace Tests\Unit\AiConnector;

use App\Domain\AiConnector\ArgumentRedactor;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ArgumentRedactorTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function sensitiveKeys(): iterable
    {
        foreach (['password', 'new_password', 'passphrase', 'api_key', 'apiKey', 'api-key', 'secret', 'client_secret', 'access_token', 'Authorization', 'private_key', 'cookie', 'credentials'] as $key) {
            yield $key => [$key];
        }
    }

    #[DataProvider('sensitiveKeys')]
    public function test_a_sensitive_value_is_replaced(string $key): void
    {
        $this->assertSame([$key => ArgumentRedactor::REDACTED], new ArgumentRedactor()->redact([$key => 'hunter2']));
    }

    public function test_the_redaction_reaches_nested_arguments(): void
    {
        $redacted = new ArgumentRedactor()->redact(['page' => 'about', 'blocks' => [['type' => 'text', 'auth' => ['token' => 'abc']]]]);

        $this->assertSame(['page' => 'about', 'blocks' => [['type' => 'text', 'auth' => ['token' => ArgumentRedactor::REDACTED]]]], $redacted);
    }

    public function test_harmless_values_are_kept_as_they_are(): void
    {
        $arguments = ['title' => 'About us', 'limit' => 10, 'ratio' => 1.5, 'draft' => true, 'note' => null, 'tags' => ['a', 'b']];

        $this->assertSame($arguments, new ArgumentRedactor()->redact($arguments));
    }

    public function test_a_long_string_is_cut_so_that_an_image_does_not_fill_the_log(): void
    {
        $redacted = new ArgumentRedactor(maxStringLength: 10)->redact(['image' => str_repeat('A', 25)]);

        $this->assertSame(str_repeat('A', 10) . '…[truncated 15 characters]', $redacted['image']);
    }

    public function test_an_object_is_replaced_by_its_type_and_deep_nesting_is_cut(): void
    {
        $redacted = new ArgumentRedactor(maxDepth: 1)->redact(['when' => new DateTimeImmutable(), 'a' => ['b' => ['c' => 1]]]);

        $this->assertSame(['when' => '[DateTimeImmutable]', 'a' => ['b' => '[nested]']], $redacted);
    }
}
