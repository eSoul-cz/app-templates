<?php

declare(strict_types=1);

namespace App\Domain\AiConnector;

/** Result of one tool call in the audit log. */
enum ToolCallStatus: string
{
    case Succeeded = 'succeeded';

    /** The call was refused: a missing scope, a missing permission or a user who may no longer connect. */
    case Denied = 'denied';

    /** The tool ended with an error (an error response or an exception). */
    case Failed = 'failed';
}
