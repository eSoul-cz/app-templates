<?php

declare(strict_types=1);

namespace App\Domain\Cms;

use RuntimeException;

/** A page revision cannot be restored now, for example because another page took its slug since. */
final class PageRevisionNotRestorable extends RuntimeException {}
