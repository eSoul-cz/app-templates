<?php

declare(strict_types=1);

use ESoul\CodingStandard\Config;
use PhpCsFixer\Finder;

// layers/ holds the template files (each layer brings its own tooling config); build/ is output.
return Config::create(
    Finder::create()->files()->in([__DIR__ . '/src', __DIR__ . '/tests'])->append([__DIR__ . '/bin/templates', __FILE__]),
    __DIR__ . '/build/php-cs-fixer.cache',
);
