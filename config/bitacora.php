<?php

declare(strict_types=1);

/**
 * Catálogo de acciones de la bitácora (HU-05). Cada acción que se registra con
 * Bitacora::registrar('modulo.accion', ...) se agrega aquí con su texto, su grupo
 * y su tono (ok, aviso, peligro, neutra) para que la pantalla la muestre legible.
 * Una acción que no esté aquí se ve igual, con su nombre técnico.
 */
return [
    'grupos' => [
        'sesion' => 'Sesiones y accesos',
        'usuarios' => 'Usuarios',
        'configuracion' => 'Configuración',
        'catalogo' => 'Catálogo',
        'caja' => 'Caja',
        'ventas' => 'Ventas y tickets',
        'qr' => 'Pagos QR',
    ],

    // accion => [texto, grupo, tono]
    'acciones' => [
        'sesion.inicio' => ['Inicio de sesión', 'sesion', 'ok'],
        'sesion.salida' => ['Cierre de sesión', 'sesion', 'neutra'],
        'sesion.inactividad' => ['Sesión cerrada por inactividad', 'sesion', 'neutra'],
        'sesion.fallo' => ['Intento de ingreso fallido', 'sesion', 'aviso'],
        'sesion.bloqueo' => ['Acceso bloqueado 15 minutos', 'sesion', 'peligro'],
        'acceso.denegado' => ['Acceso denegado', 'sesion', 'peligro'],

        'usuario.alta' => ['Alta de usuario', 'usuarios', 'neutra'],
        'usuario.rol' => ['Cambio de rol', 'usuarios', 'aviso'],
        'usuario.activar' => ['Usuario activado', 'usuarios', 'neutra'],
        'usuario.desactivar' => ['Usuario desactivado', 'usuarios', 'aviso'],
        'usuario.desbloquear' => ['Usuario desbloqueado', 'usuarios', 'neutra'],
        'usuario.reseteo_clave' => ['Reseteo de contraseña', 'usuarios', 'aviso'],
        'usuario.reseteo_pin' => ['Reseteo de PIN', 'usuarios', 'aviso'],
        'usuario.cambio_clave' => ['Cambio de contraseña', 'usuarios', 'neutra'],

        'config.negocio' => ['Datos del negocio', 'configuracion', 'neutra'],
        'config.fiscal' => ['Parámetros fiscales', 'configuracion', 'aviso'],
        'config.tickets' => ['Configuración de tickets en PDF', 'configuracion', 'neutra'],
        'config.turnos' => ['Horario de turnos', 'configuracion', 'neutra'],
        'config.turnos.agregar' => ['Turno agregado', 'configuracion', 'neutra'],
        'config.turnos.eliminar' => ['Turno eliminado', 'configuracion', 'aviso'],
        'leyenda.alta' => ['Leyenda agregada', 'configuracion', 'neutra'],
        'leyenda.activar' => ['Leyenda activada', 'configuracion', 'neutra'],
        'leyenda.desactivar' => ['Leyenda desactivada', 'configuracion', 'aviso'],

        'categoria.alta' => ['Categoría nueva', 'catalogo', 'neutra'],
        'categoria.edicion' => ['Categoría editada', 'catalogo', 'neutra'],
        'categoria.activar' => ['Categoría activada', 'catalogo', 'neutra'],
        'categoria.desactivar' => ['Categoría desactivada', 'catalogo', 'aviso'],
        'producto.alta' => ['Producto nuevo', 'catalogo', 'neutra'],
        'producto.edicion' => ['Producto editado', 'catalogo', 'neutra'],
        'producto.precio' => ['Cambio de precio', 'catalogo', 'aviso'],
        'producto.favorito' => ['Favorito', 'catalogo', 'neutra'],
        'producto.agotado' => ['Agotado', 'catalogo', 'neutra'],

        'caja.apertura' => ['Apertura de caja', 'caja', 'ok'],
        'caja.cierre' => ['Cierre de caja', 'caja', 'ok'],
        'caja.movimiento' => ['Ingreso o egreso de efectivo', 'caja', 'neutra'],
        'caja.reapertura' => ['Reapertura de caja', 'caja', 'aviso'],
        'caja.limpiar_historial' => ['Historial de cajas borrado', 'caja', 'peligro'],

        // Llegan con sus historias (Sprint 3 a 5)
        'venta.anulacion' => ['Anulación de venta', 'ventas', 'peligro'],
        'venta.descuento_manual' => ['Descuento manual', 'ventas', 'aviso'],
        'impresion.reimpresion' => ['Reimpresión', 'ventas', 'aviso'],
        'qr.subir' => ['QR del día subido', 'qr', 'neutra'],
        'qr.confirmar' => ['Pago QR confirmado', 'qr', 'ok'],
        'qr.rechazar' => ['Pago QR rechazado', 'qr', 'peligro'],
    ],

    // Las que nombra RF-04: anulaciones, descuentos manuales, reimpresiones,
    // confirmación de QR, cambios de precio, apertura y cierre de caja
    'sensibles' => [
        'venta.anulacion', 'venta.descuento_manual', 'impresion.reimpresion',
        'qr.confirmar', 'qr.rechazar', 'producto.precio',
        'caja.apertura', 'caja.cierre', 'caja.reapertura',
    ],

    // Nombres legibles de los campos del detalle
    'campos' => [
        'razon_social' => 'Razón social', 'nombre_comercial' => 'Nombre comercial', 'nit' => 'NIT',
        'direccion' => 'Dirección', 'telefono' => 'Teléfono', 'municipio' => 'Municipio',
        'codigo_sucursal' => 'Sucursal', 'codigo_punto_venta' => 'Punto de venta',
        'actividad_economica' => 'Actividad', 'cuis' => 'CUIS', 'cufd' => 'CUFD',
        'cufd_codigo_control' => 'Código de control', 'cufd_vigencia' => 'Vigencia del CUFD',
        'numero_inicial' => 'Número inicial', 'modalidad' => 'Modalidad',
        'fondo_inicial' => 'Fondo inicial', 'monto' => 'Monto', 'motivo' => 'Motivo',
        'nombre' => 'Nombre', 'orden' => 'Orden', 'color' => 'Color', 'codigo' => 'Código',
        'precio' => 'Precio', 'turno' => 'Turno', 'cajero' => 'Cajero', 'usuario' => 'Usuario',
        'rol' => 'Rol', 'modo' => 'Con', 'intentos' => 'Intentos', 'hasta' => 'Hasta',
        'ruta' => 'Ruta', 'metodo' => 'Método', 'permiso' => 'Permiso', 'favorito' => 'Favorito',
        'agotado' => 'Agotado', 'borradas' => 'Cajas borradas', 'texto' => 'Texto', 'activo' => 'Activa', 'mediodia' => 'Medio día', 'noche' => 'Noche',
    ],
];
