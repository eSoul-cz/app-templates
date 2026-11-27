<?php

declare(strict_types=1);

namespace App\Application\Contract\AiConnector;

use App\Application\Dto\AiConnector\ToolCallRecord;

/** Where every call of an AI connector tool is recorded. The tools call this port; they never write a model. */
interface ToolCallAuditLog
{
    public function record(ToolCallRecord $call): void;
}
