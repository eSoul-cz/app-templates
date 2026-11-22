<?php

declare(strict_types=1);

namespace ESoul\AppTemplates\Command;

use LogicException;
use Symfony\Component\Console\Input\InputInterface;

/** Reads an option that always holds a string: it takes a value (`VALUE_REQUIRED`) and has a default. */
final readonly class StringOption
{
    public static function get(InputInterface $input, string $name): string {
        $value = $input->getOption($name);
        if ( ! is_string($value)) {
            throw new LogicException(sprintf('Option --%s must have a string value.', $name));
        }

        return $value;
    }
}
