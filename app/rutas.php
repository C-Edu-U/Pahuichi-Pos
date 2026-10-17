<?php

declare(strict_types=1);

/**
 * Rutas del sistema. Middleware por alias (ver App::MIDDLEWARE); CSRF corre en todas.
 *
 * @var App\Core\Router $router
 */

use App\Controllers\AuthController;
use App\Controllers\CajaController;
use App\Controllers\CategoriaController;
use App\Controllers\ConfigController;
use App\Controllers\FacturaController;
use App\Controllers\InicioController;
use App\Controllers\ModuloController;
use App\Controllers\PosController;
use App\Controllers\ProductoController;
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

// Configuración (HU-08, HU-09, HU-11)
$config = ['auth', 'permiso:modulo.config'];
$router->get('/config', [ConfigController::class, 'index'], $config);
$router->post('/config/negocio', [ConfigController::class, 'negocio'], $config);
$router->post('/config/fiscal', [ConfigController::class, 'fiscal'], $config);
$router->post('/config/turnos', [ConfigController::class, 'turnos'], $config);
$router->get('/negocio/logo', [ConfigController::class, 'logo']);

// Catálogo (HU-13, HU-14): todos los de caja lo ven; solo el administrador lo edita
$catalogo = ['auth', 'permiso:modulo.catalogo'];
$editarCatalogo = [...$catalogo, 'permiso:catalogo.editar'];
$router->get('/catalogo', [InicioController::class, 'catalogo'], $catalogo);
$router->get('/catalogo/productos', [ProductoController::class, 'index'], $catalogo);
$router->get('/catalogo/productos/nuevo', [ProductoController::class, 'nuevo'], $editarCatalogo);
$router->post('/catalogo/productos/nuevo', [ProductoController::class, 'crear'], $editarCatalogo);
$router->get('/catalogo/productos/{id}/editar', [ProductoController::class, 'editar'], $editarCatalogo);
$router->post('/catalogo/productos/{id}', [ProductoController::class, 'actualizar'], $editarCatalogo);
$router->post('/catalogo/productos/{id}/favorito', [ProductoController::class, 'favorito'], $editarCatalogo);
$router->post('/catalogo/productos/{id}/agotado', [ProductoController::class, 'agotado'], [...$catalogo, 'permiso:catalogo.agotado']);
$router->get('/catalogo/categorias', [CategoriaController::class, 'index'], $catalogo);
$router->post('/catalogo/categorias', [CategoriaController::class, 'crear'], $editarCatalogo);
$router->get('/catalogo/categorias/{id}/editar', [CategoriaController::class, 'editar'], $editarCatalogo);
$router->post('/catalogo/categorias/{id}', [CategoriaController::class, 'actualizar'], $editarCatalogo);
$router->post('/catalogo/categorias/{id}/estado', [CategoriaController::class, 'estado'], $editarCatalogo);

// Caja (HU-20)
$router->get('/caja', [CajaController::class, 'index'], ['auth', 'permiso:modulo.caja']);
$router->post('/caja/abrir', [CajaController::class, 'abrir'], ['auth', 'permiso:modulo.caja']);

// Punto de venta (HU-21): sin caja abierta no se vende
$router->get('/pos', [PosController::class, 'index'], ['auth', 'permiso:modulo.pos', 'caja']);

// Leyendas y soporte de venta/factura (HU-10)
$router->post('/config/leyendas', [ConfigController::class, 'agregarLeyenda'], $config);
$router->post('/config/leyendas/{id}/estado', [ConfigController::class, 'estadoLeyenda'], $config);
$router->post('/pos/cobrar', [PosController::class, 'cobrar'], ['auth', 'permiso:modulo.pos', 'caja']);
$ventas = ['auth', 'permiso:modulo.ventas'];
$router->get('/ventas', [FacturaController::class, 'index'], $ventas);
$router->get('/facturas/{id}', [FacturaController::class, 'mostrar'], $ventas);
$router->post('/facturas/{id}/reimpresion', [FacturaController::class, 'reimprimir'], $ventas);
// Ruta pública del QR propio; verifica NIT, CUF, número e importe, sin acciones de escritura.
$router->get('/verificar', [FacturaController::class, 'verificar']);

// Módulos aún sin construir: pantalla «próximamente»
foreach (Config::get('modulos') as $clave => $m) {
    if (isset($m['pendiente'])) {
        $router->get($m['ruta'], [ModuloController::class, 'pendiente'], ['auth', "permiso:modulo.{$clave}"]);
    }
}
