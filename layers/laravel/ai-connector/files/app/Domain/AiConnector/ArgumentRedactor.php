<?php

declare(strict_types=1);

namespace App\Domain\AiConnector;

/**
 * Prepares the arguments of a tool call for the audit log: values under sensitive keys are replaced and long
 * strings (an image as base64, a page body) are cut, so the log neither keeps a secret nor grows without limit.
 */
final readonly class ArgumentRedactor
{
    public const string REDACTED = '[redacted]';

    private const string SENSITIVE_KEY = '/pass(word|phrase)?|secret|token|authorization|api[-_]?key|credential|cookie|private|signature|otp/i';

    public function __construct(
        private int $maxStringLength = 200,
        private int $maxDepth = 6,
    ) {}

    /**
     * @param  array<array-key, mixed>  $arguments
     * @return array<array-key, mixed>
     */
    public function redact(array $arguments, int $depth = 0): array
    {
        $redacted = [];

        foreach ($arguments as $key => $value) {
            $redacted[$key] = match (true) {
                is_string($key) && preg_match(self::SENSITIVE_KEY, $key) === 1 => self::REDACTED,
                is_array($value) => $depth >= $this->maxDepth ? '[nested]' : $this->redact($value, $depth + 1),
                is_string($value) => $this->truncate($value),
                is_scalar($value) || $value === null => $value,
                default => '[' . get_debug_type($value) . ']',
            };
        }

        return $redacted;
    }

    private function truncate(string $value): string
    {
        return mb_strlen($value) > $this->maxStringLength
            ? mb_substr($value, 0, $this->maxStringLength) . '…[truncated ' . (mb_strlen($value) - $this->maxStringLength) . ' characters]'
            : $value;
    }
}
