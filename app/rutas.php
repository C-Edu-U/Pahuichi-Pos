<?php

declare(strict_types=1);

/**
 * Rutas del sistema. Middleware por alias (ver App::MIDDLEWARE); CSRF corre en todas.
 *
 * @var App\Core\Router $router
 */

use App\Controllers\AuthController;
use App\Controllers\AutorizacionController;
use App\Controllers\BitacoraController;
use App\Controllers\CajaController;
use App\Controllers\CategoriaController;
use App\Controllers\ClienteController;
use App\Controllers\ConfigController;
use App\Controllers\InicioController;
use App\Controllers\ModuloController;
use App\Controllers\QrController;
use App\Controllers\ConciliacionController;
use App\Controllers\PosController;
use App\Controllers\PromocionController;
use App\Controllers\TableroController;
use App\Controllers\FacturaController;
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
// HU-06: el administrador elige su propio PIN de autorización
$router->post('/usuarios/mi-pin', [UsuarioController::class, 'miPin'], $usuarios);

// Configuración (HU-08, HU-09, HU-10, HU-11)
$config = ['auth', 'permiso:modulo.config'];
$router->get('/config', [ConfigController::class, 'index'], $config);
// HU-41: conciliacion del administrador y avisos privados a caja.
$router->get('/conciliacion', [ConciliacionController::class, 'index'], ['auth','permiso:conciliacion.resolver']);
$router->post('/conciliacion/{id}/resolver', [ConciliacionController::class, 'resolver'], ['auth','permiso:conciliacion.resolver']);
$router->get('/conciliacion/{id}/foto', [ConciliacionController::class, 'foto'], ['auth','permiso:conciliacion.resolver']);
$router->get('/conciliacion/avisos', [ConciliacionController::class, 'avisos'], ['auth','permiso:modulo.caja']);
$router->post('/conciliacion/avisos/{id}/visto', [ConciliacionController::class, 'enterado'], ['auth','permiso:modulo.caja']);
$router->get('/qr', [QrController::class, 'index'], ['auth', 'permiso:modulo.qr']);
$router->post('/qr/subir', [QrController::class, 'subir'], ['auth', 'permiso:qr.subir']);
$router->post('/qr/simular', [QrController::class, 'simular'], ['auth', 'permiso:qr.subir']);
$router->post('/qr/comprobantes/demostrar', [QrController::class, 'demostrarComprobante'], ['auth', 'permiso:qr.subir']);
$router->get('/qr/estado', [QrController::class, 'estado'], ['auth', 'permiso:modulo.caja']);
$router->get('/qr/{id}/imagen', [QrController::class, 'imagen'], ['auth', 'permiso:modulo.caja']);
$router->post('/config/negocio', [ConfigController::class, 'negocio'], $config);
$router->post('/config/fiscal', [ConfigController::class, 'fiscal'], $config);
$router->post('/config/caja-cobro', [ConfigController::class, 'cajaCobro'], $config);
$router->post('/config/turnos', [ConfigController::class, 'turnos'], $config);
$router->post('/config/tickets/{destino}', [ConfigController::class, 'tickets'], $config);
// Leyendas de la Ley 453 (HU-10)
$router->post('/config/leyendas', [ConfigController::class, 'agregarLeyenda'], $config);
$router->post('/config/leyendas/{id}/estado', [ConfigController::class, 'estadoLeyenda'], $config);
$router->get('/negocio/logo', [ConfigController::class, 'logo']);

// Catálogo (HU-13, HU-14): todos los de caja lo ven; solo el administrador lo edita
$catalogo = ['auth', 'permiso:modulo.catalogo'];
$editarCatalogo = [...$catalogo, 'permiso:catalogo.editar'];
$router->get('/catalogo', [InicioController::class, 'catalogo'], $catalogo);
$router->get('/catalogo/productos', [ProductoController::class, 'index'], $catalogo);
$router->get('/catalogo/productos/{id}/foto', [ProductoController::class, 'foto'], $catalogo);
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
$router->post('/caja/cerrar', [CajaController::class, 'cerrar'], ['auth', 'permiso:modulo.caja']);
$router->post('/caja/movimientos', [CajaController::class, 'movimiento'], ['auth', 'permiso:caja.movimiento']);
$router->get('/caja/cierre', [CajaController::class, 'cierre'], ['auth', 'permiso:modulo.caja']);
$router->post('/caja/conteo', [CajaController::class, 'conteo'], ['auth', 'permiso:modulo.caja']);
$router->get('/caja/arqueo', [CajaController::class, 'arqueo'], ['auth', 'permiso:modulo.caja']);
$router->post('/caja/arqueo', [CajaController::class, 'confirmarArqueo'], ['auth', 'permiso:modulo.caja']);
$router->get('/caja/reporte/{id}', [CajaController::class, 'reporte'], ['auth', 'permiso:modulo.caja']);
$router->post('/caja/reabrir/{id}', [CajaController::class, 'reabrir'], ['auth', 'permiso:caja.reabrir']);
$router->post('/caja/limpiar-historial', [CajaController::class, 'limpiarHistorial'], ['auth', 'permiso:caja.limpiar_historial']);

// Punto de venta (HU-21): sin caja abierta no se vende
$router->get('/pos', [PosController::class, 'index'], ['auth', 'permiso:modulo.pos', 'caja']);

// HU-57: cobro mínimo y consulta persistente. CSRF se aplica globalmente.
$router->post('/pos/cobrar', [PosController::class, 'cobrar'], ['auth', 'permiso:modulo.pos']);
// HU-06: PIN del administrador para el descuento manual (y la anulación de HU-44)
$router->post('/autorizaciones', [AutorizacionController::class, 'crear'], ['auth', 'permiso:modulo.pos']);
$router->get('/ventas', [FacturaController::class, 'index'], ['auth', 'permiso:modulo.ventas']);
$router->get('/facturas/{id}', [FacturaController::class, 'mostrar'], ['auth', 'permiso:modulo.ventas']);
$router->post('/facturas/{id}/cobrar-saldo', [FacturaController::class, 'cobrarSaldo'], ['auth', 'permiso:modulo.ventas']);

// Tablero del día (HU-64): cifras para el administrador; su turno, sin montos, para el cajero
$router->get('/tablero', [TableroController::class, 'index'], ['auth', 'permiso:modulo.tablero']);

// Promociones (HU-46): solo el administrador; se pausan, no se borran
$promociones = ['auth', 'permiso:modulo.promociones'];
$router->get('/promociones', [PromocionController::class, 'index'], $promociones);
$router->get('/promociones/nueva', [PromocionController::class, 'nueva'], $promociones);
$router->post('/promociones/nueva', [PromocionController::class, 'crear'], $promociones);
$router->get('/promociones/{id}/editar', [PromocionController::class, 'editar'], $promociones);
$router->post('/promociones/{id}', [PromocionController::class, 'actualizar'], $promociones);
$router->post('/promociones/{id}/estado', [PromocionController::class, 'estado'], $promociones);

// Bitácora (HU-05): solo lectura, solo el administrador
$router->get('/bitacora', [BitacoraController::class, 'index'], ['auth', 'permiso:modulo.bitacora']);

// HU-32: catálogo y autocompletado; administrador y cajero. CSRF global para el alta.
$clientes = ['auth', 'permiso:modulo.clientes'];
$router->get('/clientes', [ClienteController::class, 'index'], $clientes);
$router->get('/clientes/buscar', [ClienteController::class, 'buscar'], $clientes);
$router->post('/clientes', [ClienteController::class, 'crear'], [...$clientes, 'permiso:clientes.crear']);

// Módulos aún sin construir: pantalla «próximamente»
foreach (Config::get('modulos') as $clave => $m) {
    if (isset($m['pendiente'])) {
        $router->get($m['ruta'], [ModuloController::class, 'pendiente'], ['auth', "permiso:modulo.{$clave}"]);
    }
}

// Flujo recuperado de HU-10: tickets guardados y verificación del QR académico.
$router->post('/facturas/{id}/reimpresion', [FacturaController::class, 'reimprimir'], ['auth', 'permiso:modulo.ventas']);
$router->get('/verificar', [FacturaController::class, 'verificar']);

// HU-12: salida PDF con perfil de caja o cocina; mantiene los permisos de la factura.
$router->get('/facturas/{id}/pdf', [FacturaController::class, 'pdf'], ['auth', 'permiso:modulo.ventas']);
