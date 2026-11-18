<?php

declare(strict_types=1);

namespace ESoul\AppTemplates;

use RuntimeException;

/** Any error caused by wrong layer, variant or file content (not by a bug in the builder). */
final class TemplateException extends RuntimeException
{
}
