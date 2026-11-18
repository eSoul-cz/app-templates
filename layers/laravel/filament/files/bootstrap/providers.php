<?php

declare(strict_types=1);

use App\Infrastructure\Providers\AppServiceProvider;
use App\Infrastructure\Providers\Filament\AdminPanelProvider;

// Service providers of the application. They belong in App\Infrastructure\Providers: a provider
// uses the container and facades, which App\Domain must not use.
//
// This file is owned by the LAST template layer of the variant that touches it: a layer that adds
// a provider overwrites the whole list instead of patching it, so the list stays explicit.
return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
];
