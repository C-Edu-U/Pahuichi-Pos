<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Reloj;
use App\Models\Turno;
use DateTimeImmutable;

/**
 * Reportes de ventas por fecha y turno (HU-63, RF-59). Solo cuentan las ventas cobradas:
 * una venta anulada aparece en «Anulaciones» y en el libro de ventas con estado A, y en
 * ningún otro total.
 *
 * Cada reporte es una tabla (columnas, filas, total) que la pantalla, el PDF y el Excel
 * dibujan igual: los tres salen de los mismos números.
 */
final class ReporteService
{
    /** Rango máximo de un reporte, en días. */
    public const DIAS_MAXIMOS = 366;

    /** Débito fiscal del IVA sobre la base (los precios incluyen impuestos). */
    private const TASA_IVA = 0.13;

    /**
     * Filtros válidos a partir de lo que llega en la consulta: fechas AAAA-MM-DD (por
     * defecto, hoy) y turno (vacío = todos). Devuelve los filtros y el error, si hay.
     *
     * @return array{0: array{desde: string, hasta: string, turno: ?int, turno_nombre: string}, 1: ?string}
     */
    public static function filtros(string $desde, string $hasta, string $turno): array
    {
        $hoy = Reloj::ahora()->format('Y-m-d');
        $desde = self::fecha($desde) ?? $hoy;
        $hasta = self::fecha($hasta) ?? $desde;
        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }
        $t = ctype_digit($turno) ? Turno::buscar((int) $turno) : null;
        $filtros = ['desde' => $desde, 'hasta' => $hasta, 'turno' => $t !== null ? (int) $t['id'] : null, 'turno_nombre' => $t['nombre'] ?? 'Todos los turnos'];

        $dias = (new DateTimeImmutable($desde))->diff(new DateTimeImmutable($hasta))->days + 1;
        $error = $dias > self::DIAS_MAXIMOS ? 'El rango va hasta ' . self::DIAS_MAXIMOS . ' días. Elige fechas más cercanas.' : null;
        return [$filtros, $error];
    }

    /** Todos los reportes del rango: resumen y una tabla por reporte. */
    public static function generar(array $f): array
    {
        $resumen = self::resumen($f);
        return [
            'filtros' => $f,
            'resumen' => $resumen,
            'secciones' => [
                self::porProducto($f, $resumen),
                self::porCategoria($f, $resumen),
                self::porMedioDePago($f),
                self::porCajero($f),
                self::promociones($f),
                self::anulaciones($f),
                self::libroDeVentas($f),
            ],
        ];
    }

    // ------------------------------------------------------------------ consultas

    /** Ventas del rango (y del turno): v = ventas, c = cajas. */
    private static function donde(array $f, string $estado = 'cobrada'): array
    {
        $sql = ' WHERE v.creado_en >= ? AND v.creado_en < ? AND v.estado = ?';
        $params = [$f['desde'] . ' 00:00:00', (new DateTimeImmutable($f['hasta']))->modify('+1 day')->format('Y-m-d') . ' 00:00:00', $estado];
        if ($f['turno'] !== null) {
            $sql .= ' AND c.turno_id = ?';
            $params[] = $f['turno'];
        }
        return [$sql, $params];
    }

    private static function resumen(array $f): array
    {
        [$donde, $p] = self::donde($f);
        $v = DB::fila('SELECT COUNT(*) AS ventas, COALESCE(SUM(v.total), 0) AS total, COALESCE(SUM(v.descuento), 0) AS manual
            FROM ventas v JOIN cajas c ON c.id = v.caja_id' . $donde, $p) ?? [];
        $promo = DB::valor('SELECT COALESCE(SUM(d.descuento), 0) FROM detalle_venta d JOIN ventas v ON v.id = d.venta_id JOIN cajas c ON c.id = v.caja_id' . $donde, $p);
        [$dondeA, $pA] = self::donde($f, 'anulada');
        $a = DB::fila('SELECT COUNT(*) AS ventas, COALESCE(SUM(v.total), 0) AS total FROM ventas v JOIN cajas c ON c.id = v.caja_id' . $dondeA, $pA) ?? [];

        $ventas = (int) ($v['ventas'] ?? 0);
        $total = self::monto($v['total'] ?? 0);
        return [
            ['Ventas cobradas', $ventas, 'entero'],
            ['Total vendido', $total, 'monto'],
            ['Ticket promedio', $ventas > 0 ? round($total / $ventas, 2) : 0.0, 'monto'],
            ['Descuentos de promociones', self::monto($promo), 'monto'],
            ['Descuentos manuales', self::monto($v['manual'] ?? 0), 'monto'],
            ['Ventas anuladas', (int) ($a['ventas'] ?? 0), 'entero'],
            ['Monto anulado', self::monto($a['total'] ?? 0), 'monto'],
        ];
    }

    /** Valor de una fila del resumen por su etiqueta. */
    public static function delResumen(array $resumen, string $etiqueta): int|float
    {
        foreach ($resumen as [$texto, $valor]) {
            if ($texto === $etiqueta) {
                return $valor;
            }
        }
        return 0;
    }

    /** La línea es un regalo por compra (HU-48): sale a Bs 0 y se cuenta aparte. */
    private const ES_REGALO = "EXISTS (SELECT 1 FROM venta_promociones vp JOIN promociones pr ON pr.id = vp.promocion_id
        WHERE vp.detalle_id = d.id AND pr.tipo = 'regalo')";

    private static function porProducto(array $f, array $resumen): array
    {
        [$donde, $p] = self::donde($f);
        $filas = DB::filas('SELECT p.nombre, cat.nombre AS categoria,
                SUM(CASE WHEN ' . self::ES_REGALO . ' THEN 0 ELSE d.cantidad END) AS unidades,
                SUM(CASE WHEN ' . self::ES_REGALO . ' THEN d.cantidad ELSE 0 END) AS regalos,
                SUM(d.cantidad * d.precio_unitario) AS bruto, SUM(d.descuento) AS descuentos, SUM(d.subtotal) AS total
            FROM detalle_venta d JOIN ventas v ON v.id = d.venta_id JOIN cajas c ON c.id = v.caja_id
            JOIN productos p ON p.id = d.producto_id JOIN categorias cat ON cat.id = p.categoria_id' . $donde . '
            GROUP BY p.id, p.nombre, cat.nombre ORDER BY total DESC, p.nombre', $p);

        return self::seccion('productos', 'Ventas por producto', [
            ['Producto', 'texto'], ['Categoría', 'texto'], ['Unidades', 'entero'], ['Regalos', 'entero'],
            ['Bruto', 'monto'], ['Descuentos', 'monto'], ['Total', 'monto'],
        ], array_map(static fn (array $r): array => [
            $r['nombre'], $r['categoria'], (int) $r['unidades'], (int) $r['regalos'],
            self::monto($r['bruto']), self::monto($r['descuentos']), self::monto($r['total']),
        ], $filas), [2, 3, 4, 5, 6], self::notaManual($resumen));
    }

    private static function porCategoria(array $f, array $resumen): array
    {
        [$donde, $p] = self::donde($f);
        $filas = DB::filas('SELECT cat.nombre AS categoria,
                SUM(CASE WHEN ' . self::ES_REGALO . ' THEN 0 ELSE d.cantidad END) AS unidades,
                SUM(d.cantidad * d.precio_unitario) AS bruto, SUM(d.descuento) AS descuentos, SUM(d.subtotal) AS total
            FROM detalle_venta d JOIN ventas v ON v.id = d.venta_id JOIN cajas c ON c.id = v.caja_id
            JOIN productos p ON p.id = d.producto_id JOIN categorias cat ON cat.id = p.categoria_id' . $donde . '
            GROUP BY cat.id, cat.nombre ORDER BY total DESC, cat.nombre', $p);

        return self::seccion('categorias', 'Ventas por categoría', [
            ['Categoría', 'texto'], ['Unidades', 'entero'], ['Bruto', 'monto'], ['Descuentos', 'monto'], ['Total', 'monto'],
        ], array_map(static fn (array $r): array => [
            $r['categoria'], (int) $r['unidades'], self::monto($r['bruto']), self::monto($r['descuentos']), self::monto($r['total']),
        ], $filas), [1, 2, 3, 4], self::notaManual($resumen));
    }

    /** El descuento manual (HU-06) va sobre la venta, no sobre un producto: se aclara. */
    private static function notaManual(array $resumen): ?string
    {
        $manual = (float) self::delResumen($resumen, 'Descuentos manuales');
        return $manual > 0
            ? 'El descuento manual de Bs ' . bs($manual) . ' es sobre la venta y no se reparte por producto: el total vendido es este total menos ese descuento.'
            : null;
    }

    private static function porMedioDePago(array $f): array
    {
        [$donde, $p] = self::donde($f);
        $filas = DB::filas('SELECT pg.metodo, pg.estado, COUNT(DISTINCT v.id) AS ventas, SUM(pg.monto) AS monto
            FROM pagos pg JOIN ventas v ON v.id = pg.venta_id JOIN cajas c ON c.id = v.caja_id' . $donde . '
            GROUP BY pg.metodo, pg.estado', $p);
        $por = [];
        foreach ($filas as $r) {
            $por[$r['metodo'] . '.' . $r['estado']] = $r;
        }
        // Siempre los cuatro, aunque estén en cero: así se ve que no hubo QR pendientes
        $medios = ['efectivo.confirmado' => 'Efectivo', 'qr.confirmado' => 'QR confirmado', 'qr.pendiente' => 'QR pendiente', 'qr.rechazado' => 'QR rechazado'];

        return self::seccion('medios', 'Ventas por medio de pago', [
            ['Medio de pago', 'texto'], ['Ventas', 'entero'], ['Monto', 'monto'],
        ], array_map(static fn (string $clave, string $texto): array => [
            $texto, (int) ($por[$clave]['ventas'] ?? 0), self::monto($por[$clave]['monto'] ?? 0),
        ], array_keys($medios), $medios), [2], 'Un pago mixto cuenta en los dos medios con el monto de cada uno. Los QR pendientes y rechazados no son ingreso.');
    }

    private static function porCajero(array $f): array
    {
        [$donde, $p] = self::donde($f);
        $filas = DB::filas('SELECT u.nombre, COUNT(*) AS ventas, SUM(v.total) AS total
            FROM ventas v JOIN cajas c ON c.id = v.caja_id JOIN usuarios u ON u.id = v.usuario_id' . $donde . '
            GROUP BY u.id, u.nombre ORDER BY total DESC, u.nombre', $p);

        return self::seccion('cajeros', 'Ventas por cajero', [
            ['Cajero', 'texto'], ['Ventas', 'entero'], ['Total', 'monto'], ['Ticket promedio', 'monto'],
        ], array_map(static fn (array $r): array => [
            $r['nombre'], (int) $r['ventas'], self::monto($r['total']), round(self::monto($r['total']) / max(1, (int) $r['ventas']), 2),
        ], $filas), [1, 2]);
    }

    private static function promociones(array $f): array
    {
        [$donde, $p] = self::donde($f);
        $filas = DB::filas("SELECT pr.nombre, pr.tipo, COUNT(DISTINCT vp.venta_id) AS ventas,
                SUM(CASE WHEN pr.tipo = 'regalo' THEN d.cantidad ELSE 0 END) AS regalos, SUM(vp.monto) AS ahorro
            FROM venta_promociones vp JOIN detalle_venta d ON d.id = vp.detalle_id JOIN promociones pr ON pr.id = vp.promocion_id
            JOIN ventas v ON v.id = vp.venta_id JOIN cajas c ON c.id = v.caja_id" . $donde . '
            GROUP BY pr.id, pr.nombre, pr.tipo ORDER BY ahorro DESC, pr.nombre', $p);
        $tipos = ['descuento' => 'Descuento', 'regalo' => 'Regalo', 'combo' => 'Combo'];
        $lineas = array_map(static fn (array $r): array => [
            $r['nombre'], $tipos[$r['tipo']] ?? $r['tipo'], (int) $r['ventas'], (int) $r['regalos'], self::monto($r['ahorro']),
        ], $filas);

        $manual = DB::fila('SELECT COUNT(*) AS ventas, COALESCE(SUM(v.descuento), 0) AS monto FROM ventas v JOIN cajas c ON c.id = v.caja_id'
            . $donde . ' AND v.descuento > 0', $p);
        if ((int) ($manual['ventas'] ?? 0) > 0) {
            $lineas[] = ['Descuento manual con PIN', 'Manual', (int) $manual['ventas'], 0, self::monto($manual['monto'])];
        }

        return self::seccion('promociones', 'Uso de promociones', [
            ['Promoción', 'texto'], ['Tipo', 'texto'], ['Ventas', 'entero'], ['Regalos', 'entero'], ['Ahorro del cliente', 'monto'],
        ], $lineas, [3, 4]);
    }

    private static function anulaciones(array $f): array
    {
        [$donde, $p] = self::donde($f, 'anulada');
        $filas = DB::filas('SELECT v.id, v.creado_en, v.total, u.nombre AS cajero, t.nombre AS turno, f.numero
            FROM ventas v JOIN cajas c ON c.id = v.caja_id JOIN turnos t ON t.id = c.turno_id JOIN usuarios u ON u.id = v.usuario_id
            LEFT JOIN facturas f ON f.venta_id = v.id' . $donde . ' ORDER BY v.creado_en, v.id', $p);

        return self::seccion('anulaciones', 'Anulaciones', [
            ['Fecha y hora', 'texto'], ['Pedido', 'texto'], ['Factura', 'texto'], ['Turno', 'texto'], ['Cajero', 'texto'], ['Monto', 'monto'],
        ], array_map(static fn (array $r): array => [
            (new DateTimeImmutable($r['creado_en']))->format('d/m/Y H:i'), str_pad((string) $r['id'], 3, '0', STR_PAD_LEFT),
            $r['numero'] !== null ? (string) $r['numero'] : '—', $r['turno'], $r['cajero'], self::monto($r['total']),
        ], $filas), [5], 'El motivo y quién autorizó la anulación se agregan con HU-44.');
    }

    /**
     * Libro de ventas simulado, con las columnas del libro del SIN que aplican al
     * restaurante. Una factura anulada va con estado A y sus importes en cero.
     */
    private static function libroDeVentas(array $f): array
    {
        $donde = ' WHERE v.creado_en >= ? AND v.creado_en < ?';
        $p = [$f['desde'] . ' 00:00:00', (new DateTimeImmutable($f['hasta']))->modify('+1 day')->format('Y-m-d') . ' 00:00:00'];
        if ($f['turno'] !== null) {
            $donde .= ' AND c.turno_id = ?';
            $p[] = $f['turno'];
        }
        $filas = DB::filas("SELECT f.fecha_emision, f.numero, f.cuf, f.estado, v.estado AS venta_estado, v.subtotal, v.descuento, v.total,
                JSON_VALUE(f.snapshot_json, '$.cliente.doc') AS doc, JSON_VALUE(f.snapshot_json, '$.cliente.nombre') AS cliente,
                (SELECT COALESCE(SUM(d.descuento), 0) FROM detalle_venta d WHERE d.venta_id = v.id) AS promo
            FROM facturas f JOIN ventas v ON v.id = f.venta_id JOIN cajas c ON c.id = v.caja_id" . $donde . '
            ORDER BY f.fecha_emision, f.numero', $p);

        $n = 0;
        $lineas = array_map(static function (array $r) use (&$n): array {
            $anulada = $r['estado'] === 'anulada' || $r['venta_estado'] === 'anulada';
            $base = $anulada ? 0.0 : self::monto($r['total']);
            return [
                ++$n,
                (new DateTimeImmutable($r['fecha_emision']))->format('d/m/Y'),
                (string) $r['numero'],
                $r['cuf'],
                (string) ($r['doc'] ?? '0'),
                (string) ($r['cliente'] ?? 'S/N'),
                $anulada ? 0.0 : self::monto((float) $r['subtotal'] + (float) $r['promo']),
                $anulada ? 0.0 : self::monto((float) $r['promo'] + (float) $r['descuento']),
                $base,
                round($base * self::TASA_IVA, 2),
                $anulada ? 'A' : 'V',
            ];
        }, $filas);

        return self::seccion('libro', 'Libro de ventas (simulado)', [
            ['N.º', 'entero'], ['Fecha', 'texto'], ['Factura', 'texto'], ['Código de autorización (CUF)', 'texto'],
            ['NIT/CI', 'texto'], ['Nombre o razón social', 'texto'], ['Importe total', 'monto'], ['Descuentos', 'monto'],
            ['Base para débito fiscal', 'monto'], ['Débito fiscal (13 %)', 'monto'], ['Estado', 'texto'],
        ], $lineas, [6, 7, 8, 9], 'Documento sin valor fiscal. Estado V = válida, A = anulada (importes en cero).');
    }

    // ------------------------------------------------------------------ armado

    /**
     * Una tabla: columnas [texto, tipo], filas y la fila de total con la suma de las
     * columnas indicadas (en centavos, para que no se escape un centavo).
     */
    private static function seccion(string $clave, string $titulo, array $columnas, array $filas, array $sumar, ?string $nota = null): array
    {
        $total = null;
        if ($filas !== [] && $sumar !== []) {
            $total = array_fill(0, count($columnas), null);
            $total[0] = 'Total';
            foreach ($sumar as $i) {
                $centavos = 0;
                foreach ($filas as $fila) {
                    $centavos += (int) round((float) $fila[$i] * 100);
                }
                $total[$i] = $columnas[$i][1] === 'entero' ? intdiv($centavos, 100) : round($centavos / 100, 2);
            }
        }
        return ['clave' => $clave, 'titulo' => $titulo, 'columnas' => $columnas, 'filas' => $filas, 'total' => $total, 'nota' => $nota];
    }

    private static function monto(mixed $valor): float
    {
        return round((float) $valor, 2);
    }

    private static function fecha(string $texto): ?string
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $texto);
        return $d !== false && $d->format('Y-m-d') === $texto ? $texto : null;
    }
}
