<?php

declare(strict_types=1);

/**
 * Rutas del sistema. Middleware por alias (ver App::MIDDLEWARE); CSRF corre en todas.
 *
 * @var App\Core\Router $router
 */

use App\Controllers\ModuloController;
use App\Controllers\SaludController;
use App\Core\Config;

$router->get('/salud', [SaludController::class, 'index']);

// Inicio
$router->get('/', [App\Controllers\InicioController::class, 'index']);

// Módulos aún sin construir: pantalla «próximamente»
foreach (Config::get('modulos') as $clave => $m) {
    if (isset($m['pendiente'])) {
        $router->get($m['ruta'], [ModuloController::class, 'pendiente']);
    }
}
