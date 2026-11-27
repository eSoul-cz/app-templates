<?php

declare(strict_types=1);

namespace App\Infrastructure\Models;

use App\Domain\AiConnector\ToolCallStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One row of the audit log of the AI connector: written through App\Application\Contract\AiConnector\ToolCallAuditLog
 * only, never changed afterwards.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $client_id
 * @property string|null $client_name
 * @property string $tool
 * @property array<array-key, mixed> $arguments
 * @property ToolCallStatus $status
 * @property string|null $detail
 * @property int $duration_ms
 * @property Carbon $called_at
 */
#[Fillable(['user_id', 'client_id', 'client_name', 'tool', 'arguments', 'status', 'detail', 'duration_ms', 'called_at'])]
class AiToolCall extends Model
{
    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'arguments' => 'array',
            'status' => ToolCallStatus::class,
            'called_at' => 'datetime',
        ];
    }
}
