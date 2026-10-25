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
        'clientes' => 'Clientes',
        'caja' => 'Caja',
        'ventas' => 'Ventas y tickets',
        'promociones' => 'Promociones',
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
        'autorizacion.fallo' => ['PIN de autorización errado', 'sesion', 'aviso'],
        'autorizacion.bloqueo' => ['Autorización con PIN bloqueada 15 minutos', 'sesion', 'peligro'],

        'usuario.alta' => ['Alta de usuario', 'usuarios', 'neutra'],
        'usuario.rol' => ['Cambio de rol', 'usuarios', 'aviso'],
        'usuario.activar' => ['Usuario activado', 'usuarios', 'neutra'],
        'usuario.desactivar' => ['Usuario desactivado', 'usuarios', 'aviso'],
        'usuario.desbloquear' => ['Usuario desbloqueado', 'usuarios', 'neutra'],
        'usuario.reseteo_clave' => ['Reseteo de contraseña', 'usuarios', 'aviso'],
        'usuario.reseteo_pin' => ['Reseteo de PIN', 'usuarios', 'aviso'],
        'usuario.cambio_clave' => ['Cambio de contraseña', 'usuarios', 'neutra'],
        'usuario.cambio_pin' => ['Cambio de PIN de autorización', 'usuarios', 'aviso'],

        'config.negocio' => ['Datos del negocio', 'configuracion', 'neutra'],
        'config.fiscal' => ['Parámetros fiscales', 'configuracion', 'aviso'],
        'config.tickets' => ['Configuración de tickets en PDF', 'configuracion', 'neutra'],
        'config.turnos' => ['Horario de turnos', 'configuracion', 'neutra'],
        'config.turnos.agregar' => ['Turno agregado', 'configuracion', 'neutra'],
        'config.turnos.eliminar' => ['Turno eliminado', 'configuracion', 'aviso'],
        'config.caja_cobro' => ['Parámetros de caja y cobro', 'configuracion', 'aviso'],
        'leyenda.alta' => ['Leyenda agregada', 'configuracion', 'neutra'],
        'leyenda.activar' => ['Leyenda activada', 'configuracion', 'neutra'],
        'leyenda.desactivar' => ['Leyenda desactivada', 'configuracion', 'aviso'],

        'cliente.alta' => ['Cliente nuevo', 'clientes', 'neutra'],

        'categoria.alta' => ['Categoría nueva', 'catalogo', 'neutra'],
        'categoria.edicion' => ['Categoría editada', 'catalogo', 'neutra'],
        'categoria.activar' => ['Categoría activada', 'catalogo', 'neutra'],
        'categoria.desactivar' => ['Categoría desactivada', 'catalogo', 'aviso'],
        'producto.alta' => ['Producto nuevo', 'catalogo', 'neutra'],
        'producto.edicion' => ['Producto editado', 'catalogo', 'neutra'],
        'producto.precio' => ['Cambio de precio', 'catalogo', 'aviso'],
        'producto.favorito' => ['Favorito', 'catalogo', 'neutra'],
        'producto.agotado' => ['Agotado', 'catalogo', 'neutra'],
        'producto.foto' => ['Foto del producto', 'catalogo', 'neutra'],

        'promocion.alta' => ['Promoción creada', 'promociones', 'neutra'],
        'promocion.edicion' => ['Promoción editada', 'promociones', 'aviso'],
        'promocion.activar' => ['Promoción activada', 'promociones', 'neutra'],
        'promocion.desactivar' => ['Promoción pausada', 'promociones', 'neutra'],

        'caja.apertura' => ['Apertura de caja', 'caja', 'ok'],
        'caja.cierre' => ['Cierre de caja', 'caja', 'ok'],
        'caja.movimiento' => ['Ingreso o egreso de efectivo', 'caja', 'neutra'],
        'caja.reapertura' => ['Reapertura de caja', 'caja', 'aviso'],
        'caja.limpiar_historial' => ['Historial de cajas archivado', 'caja', 'neutra'],
        'caja.ingreso' => ['Ingreso de efectivo', 'caja', 'neutra'],
        'caja.egreso' => ['Egreso de efectivo', 'caja', 'aviso'],
        'caja.conteo' => ['Conteo de efectivo', 'caja', 'neutra'],
        'caja.arqueo' => ['Arqueo de caja', 'caja', 'ok'],
        'caja.reabrir' => ['Caja reabierta', 'caja', 'aviso'],

        // Llegan con sus historias (Sprint 3 a 5)
        'venta.cobro' => ['Venta cobrada', 'ventas', 'ok'],
        'factura.reimpresion' => ['Reimpresión de tickets', 'ventas', 'aviso'],
        'venta.anulacion' => ['Anulación de venta', 'ventas', 'peligro'],
        'venta.descuento_manual' => ['Descuento manual', 'ventas', 'aviso'],
        'impresion.reimpresion' => ['Reimpresión', 'ventas', 'aviso'],
        'qr.subir' => ['QR del día subido', 'qr', 'neutra'],
        'qr.simular_vencimiento' => ['Simulación de vencimiento del QR en 5 segundos', 'qr', 'aviso'],
        'qr.restablecer_vigencia' => ['Vigencia habitual del QR restablecida', 'qr', 'neutra'],
        'qr.confirmar' => ['Pago QR confirmado', 'qr', 'ok'],
        'qr.rechazar' => ['Pago QR rechazado', 'qr', 'peligro'],
    ],

    // Las que nombra RF-04: anulaciones, descuentos manuales, reimpresiones,
    // confirmación de QR, cambios de precio, apertura y cierre de caja
    'sensibles' => [
        'venta.anulacion', 'venta.descuento_manual', 'impresion.reimpresion',
        'qr.confirmar', 'qr.rechazar', 'producto.precio',
        'caja.apertura', 'caja.cierre', 'caja.reapertura', 'caja.reabrir', 'caja.arqueo', 'factura.reimpresion',
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
        'agotado' => 'Agotado', 'borradas' => 'Cajas borradas', 'archivadas' => 'Cajas archivadas', 'accion' => 'Acción', 'autorizado_por' => 'Autorizó', 'subtotal' => 'Subtotal', 'aplica' => 'Se aplica a', 'cuando' => 'Cuándo', 'condiciones' => 'Condiciones', 'descuento' => 'Descuento', 'promociones' => 'Promociones', 'texto' => 'Texto', 'activo' => 'Activa', 'mediodia' => 'Medio día', 'noche' => 'Noche',
    ],
];
