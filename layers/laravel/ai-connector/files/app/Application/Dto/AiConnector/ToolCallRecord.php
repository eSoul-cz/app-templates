<?php

declare(strict_types=1);

namespace App\Application\Dto\AiConnector;

use App\Domain\AiConnector\ToolCallStatus;
use DateTimeImmutable;

/** One tool call as the audit log keeps it. `arguments` are already redacted. */
final readonly class ToolCallRecord
{
    /** @param  array<array-key, mixed>  $arguments */
    public function __construct(
        public ?int $userId,
        public ?string $clientId,
        public string $tool,
        public array $arguments,
        public ToolCallStatus $status,
        public int $durationMs,
        public DateTimeImmutable $calledAt,
        public ?string $detail = null,
    ) {}
}
