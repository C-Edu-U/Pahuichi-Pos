<?php

declare(strict_types=1);

/**
 * Rutas del sistema. Middleware por alias (ver App::MIDDLEWARE); CSRF corre en todas.
 *
 * @var App\Core\Router $router
 */

use App\Controllers\AuthController;
use App\Controllers\ConfigController;
use App\Controllers\InicioController;
use App\Controllers\ModuloController;
use App\Controllers\SaludController;
use App\Controllers\UsuarioController;
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

// Usuarios (HU-03)
$usuarios = ['auth', 'permiso:modulo.usuarios'];
$router->get('/usuarios', [UsuarioController::class, 'index'], $usuarios);
$router->post('/usuarios', [UsuarioController::class, 'crear'], $usuarios);
$router->post('/usuarios/{id}/rol', [UsuarioController::class, 'rol'], $usuarios);
$router->post('/usuarios/{id}/estado', [UsuarioController::class, 'estado'], $usuarios);
$router->post('/usuarios/{id}/desbloquear', [UsuarioController::class, 'desbloquear'], $usuarios);
$router->post('/usuarios/{id}/clave', [UsuarioController::class, 'resetearClave'], $usuarios);
$router->post('/usuarios/{id}/pin', [UsuarioController::class, 'resetearPin'], $usuarios);

// Configuración (HU-08)
$config = ['auth', 'permiso:modulo.config'];
$router->get('/config', [ConfigController::class, 'index'], $config);
$router->post('/config/negocio', [ConfigController::class, 'negocio'], $config);
$router->get('/negocio/logo', [ConfigController::class, 'logo']);

// Módulos aún sin construir: pantalla «próximamente»
foreach (Config::get('modulos') as $clave => $m) {
    if (isset($m['pendiente'])) {
        $router->get($m['ruta'], [ModuloController::class, 'pendiente'], ['auth', "permiso:modulo.{$clave}"]);
    }
}
