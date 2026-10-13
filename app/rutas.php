<?php

declare(strict_types=1);

/**
 * Rutas del sistema. Middleware por alias (ver App::MIDDLEWARE); CSRF corre en todas.
 *
 * @var App\Core\Router $router
 */

use App\Controllers\AuthController;
use App\Controllers\InicioController;
use App\Controllers\ModuloController;
use App\Controllers\SaludController;
use App\Core\Config;

$router->get('/salud', [SaludController::class, 'index']);

// Sesión (HU-02)
$router->get('/', [InicioController::class, 'index']);
$router->get('/login', [AuthController::class, 'mostrar'], ['invitado']);
$router->post('/login', [AuthController::class, 'entrar'], ['invitado']);
$router->post('/salir', [AuthController::class, 'salir']);
$router->post('/sesion/seguir', [AuthController::class, 'seguir'], ['auth']);
$router->get('/cambiar-clave', [AuthController::class, 'formCambiarClave'], ['auth']);
$router->post('/cambiar-clave', [AuthController::class, 'cambiarClave'], ['auth']);

// Módulos aún sin construir: pantalla «próximamente»
foreach (Config::get('modulos') as $clave => $m) {
    if (isset($m['pendiente'])) {
        $router->get($m['ruta'], [ModuloController::class, 'pendiente'], ['auth', "permiso:modulo.{$clave}"]);
    }
}
