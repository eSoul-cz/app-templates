<?php

declare(strict_types=1);

namespace App\Domain\Cms;

use RuntimeException;

/** An uploaded image is refused (not an accepted image, too large); `problems` say why. */
final class InvalidPageImage extends RuntimeException
{
    /** @param  list<string>  $problems */
    public function __construct(public readonly array $problems)
    {
        parent::__construct(implode("\n", $problems));
    }
}
