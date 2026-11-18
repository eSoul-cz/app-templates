<?php

declare(strict_types=1);

/** @var Router $this */

use App\Http\Controllers\Home;
use Lsr\Core\Routing\Router;

$routes = $this->group('/')->middlewareAll('web');

$routes->get('/', [Home::class, 'show'])->name('home');
