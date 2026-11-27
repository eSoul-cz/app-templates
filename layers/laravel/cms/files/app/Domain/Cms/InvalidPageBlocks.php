<?php

declare(strict_types=1);

namespace App\Domain\Cms;

use RuntimeException;

/** The blocks of a page do not match the block catalogue; `problems` say which block and field. */
final class InvalidPageBlocks extends RuntimeException
{
    /** @param  list<string>  $problems */
    public function __construct(public readonly array $problems)
    {
        parent::__construct(implode("\n", $problems));
    }
}
