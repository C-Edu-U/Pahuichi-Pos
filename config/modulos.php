<?php

declare(strict_types=1);

// Módulos del menú lateral, en orden y por grupos (un separador entre grupos).
// «pendiente» describe lo que muestra la pantalla «próximamente» mientras el
// módulo no esté construido; se borra cuando su historia se implementa.
return [
    'tablero' => [
        'grupo' => 1, 'ruta' => '/tablero', 'icono' => 'escritorio', 'texto' => 'Tablero',
        'pendiente' => ['historias' => 'HU-64', 'texto' => 'Ventas acumuladas del día, ticket promedio, más vendidos y QR por confirmar.'],
    ],
    'pos' => [
        'grupo' => 1, 'ruta' => '/pos', 'icono' => 'pos', 'texto' => 'Punto de venta',
    ],
    'caja' => [
        'grupo' => 1, 'ruta' => '/caja', 'icono' => 'caja', 'texto' => 'Caja',
    ],
    'ventas' => [
        'grupo' => 1, 'ruta' => '/ventas', 'icono' => 'ventas', 'texto' => 'Ventas y facturas',
    ],
    'mesero' => [
        'grupo' => 1, 'ruta' => '/mesero', 'icono' => 'mesa', 'texto' => 'Pedidos',
        'pendiente' => ['historias' => 'HU-35', 'texto' => 'El mesero toma el pedido en su celular y llega a caja para el cobro.'],
    ],
    'conciliacion' => [
        'grupo' => 2, 'ruta' => '/conciliacion', 'icono' => 'check', 'texto' => 'Conciliación QR',
        'pendiente' => ['historias' => 'HU-41, HU-43', 'texto' => 'Bandeja para confirmar o rechazar los pagos QR desde el celular.'],
    ],
    'qr' => [
        'grupo' => 2, 'ruta' => '/qr', 'icono' => 'qr', 'texto' => 'QR del día',
        'pendiente' => ['historias' => 'HU-38', 'texto' => 'Subir el QR de cobro del día, vigente hasta las 23:59:59.'],
    ],
    'clientes' => [
        'grupo' => 3, 'ruta' => '/clientes', 'icono' => 'clientes', 'texto' => 'Clientes',
        'pendiente' => ['historias' => 'HU-32', 'texto' => 'Clientes con NIT o CI para la factura.'],
    ],
    'catalogo' => [
        'grupo' => 3, 'ruta' => '/catalogo/productos', 'icono' => 'productos', 'texto' => 'Catálogo',
    ],
    'promociones' => [
        'grupo' => 3, 'ruta' => '/promociones', 'icono' => 'promocion', 'texto' => 'Promociones',
        'pendiente' => ['historias' => 'HU-46 a HU-52', 'texto' => 'Descuentos, combos, regalos y simulador.'],
    ],
    'reportes' => [
        'grupo' => 4, 'ruta' => '/reportes', 'icono' => 'reportes', 'texto' => 'Reportes',
        'pendiente' => ['historias' => 'HU-63', 'texto' => 'Ventas por fecha y turno, Libro de Ventas, exportar a PDF y Excel.'],
    ],
    'bitacora' => [
        'grupo' => 4, 'ruta' => '/bitacora', 'icono' => 'bitacora', 'texto' => 'Bitácora',
        'pendiente' => ['historias' => 'HU-05', 'texto' => 'Consulta de las acciones sensibles. El registro ya está activo.'],
    ],
    'usuarios' => [
        'grupo' => 5, 'ruta' => '/usuarios', 'icono' => 'usuarios', 'texto' => 'Usuarios',
    ],
    'config' => [
        'grupo' => 5, 'ruta' => '/config', 'icono' => 'config', 'texto' => 'Configuración',
    ],
    // Fuera del menú: se entra desde el estado de la impresora
    'impresion' => [
        'grupo' => 0, 'ruta' => '/impresion', 'icono' => 'imprimir', 'texto' => 'Cola de impresión',
        'pendiente' => ['historias' => 'HU-53', 'texto' => 'Trabajos de impresión pendientes y reintentos.'],
    ],
];
