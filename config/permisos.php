<?php

declare(strict_types=1);

/**
 * Matriz de roles y permisos (HU-04, RF-03). El servidor la aplica en cada ruta
 * con el middleware «permiso:...»; el menú filtrado es solo comodidad.
 * Cocina no tiene permisos: no entra al panel, recibe la comanda impresa.
 */
$admin = ['administrador'];
$caja = ['administrador', 'cajero'];

return [
    // Módulos del menú
    'modulo.tablero' => $caja,
    'modulo.pos' => $caja,
    'modulo.caja' => $caja,
    'modulo.ventas' => $caja,
    'modulo.mesero' => ['administrador', 'mesero'],
    'modulo.conciliacion' => $admin,
    'modulo.qr' => $caja,
    'modulo.clientes' => $caja,
    'modulo.catalogo' => $caja,
    'modulo.promociones' => $admin,
    'modulo.reportes' => $admin,
    'modulo.bitacora' => $admin,
    'modulo.usuarios' => $admin,
    'modulo.config' => $admin,
    'modulo.impresion' => $caja,

    // Acciones dentro de los módulos
    'caja.abrir_para_otro' => $admin,
    'caja.movimiento' => $caja,
    'caja.limpiar_historial' => $admin,
    'caja.reabrir' => $admin,
    'ventas.todas' => $admin,
    'ventas.anular' => $admin,
    'conciliacion.resolver' => $admin,
    'qr.subir' => $admin,
    'clientes.crear' => $caja,
    'clientes.editar' => $admin,
    'catalogo.editar' => $admin,
    'catalogo.agotado' => $caja,
    'reportes.exportar' => $admin,
    // HU-35: cuentas por mesa. El mesero toma pedidos y cobra; caja ve y cobra; el
    // descuento manual con PIN solo en caja
    'cuentas.ver' => ['administrador', 'cajero', 'mesero'],
    'cuentas.tomar' => ['administrador', 'mesero'],
    'cuentas.cobrar' => ['administrador', 'cajero', 'mesero'],
    'cuentas.descuento' => $caja,
    'catalogo.fotos' => ['administrador', 'cajero', 'mesero'],
    // El mesero también muestra el QR del día al cobrar la cuenta de una mesa
    'qr.cobrar' => ['administrador', 'cajero', 'mesero'],
];
