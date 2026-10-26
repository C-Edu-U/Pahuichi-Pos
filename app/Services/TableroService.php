<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Reloj;
use App\Models\Caja;
use App\Models\ParametrosFiscales;
use DateTimeImmutable;

/**
 * Tablero del día (HU-64, RF-60): lo que el dueño mira desde el celular. Ventas
 * acumuladas, ticket promedio, comparación con ayer a la misma hora, más vendidos,
 * pagos QR por confirmar, lo que hay que atender y la caja. Solo ventas cobradas:
 * las anuladas no cuentan.
 */
final class TableroService
{
    /** Para el administrador. */
    public static function delDia(DateTimeImmutable $ahora): array
    {
        $hoy = $ahora->setTime(0, 0);
        $ayer = $hoy->modify('-1 day');
        $ventas = self::ventas($hoy, $hoy->modify('+1 day'));
        $antes = self::ventas($ayer, $ahora->modify('-1 day'));

        return [
            'ahora' => $ahora,
            'ventas' => $ventas['cantidad'],
            'total' => $ventas['total'],
            'promedio' => $ventas['cantidad'] ? round($ventas['total'] / $ventas['cantidad'], 2) : 0.0,
            // Frente a ayer hasta la misma hora; sin ventas ayer no hay con qué comparar
            'variacion' => $antes['total'] > 0 ? round(($ventas['total'] - $antes['total']) / $antes['total'] * 100, 1) : null,
            'masVendidos' => self::masVendidos($hoy, $hoy->modify('+1 day')),
            'qr' => self::qrPendientes(),
            'caja' => self::caja(),
            'avisos' => self::avisos($ahora),
        ];
    }

    /** Para el cajero: su turno y cuántas ventas lleva, sin montos (el cierre es ciego). */
    public static function delCajero(int $cajeroId): array
    {
        $caja = Caja::abierta();
        $propia = $caja !== null && (int) $caja['cajero_id'] === $cajeroId;
        return [
            'caja' => $propia ? $caja : null,
            'otraCaja' => $caja !== null && !$propia ? $caja : null,
            'ventas' => $propia ? (int) DB::valor("SELECT COUNT(*) FROM ventas WHERE caja_id = ? AND estado = 'cobrada'", [(int) $caja['id']]) : 0,
            'qr' => self::qrPendientes(),
        ];
    }

    /** @return array{cantidad: int, total: float} */
    private static function ventas(DateTimeImmutable $desde, DateTimeImmutable $hasta): array
    {
        $f = DB::fila(
            "SELECT COUNT(*) AS cantidad, COALESCE(SUM(total), 0) AS total FROM ventas
             WHERE estado = 'cobrada' AND creado_en >= ? AND creado_en < ?",
            [Reloj::sql($desde), Reloj::sql($hasta)],
        ) ?? [];
        return ['cantidad' => (int) ($f['cantidad'] ?? 0), 'total' => (float) ($f['total'] ?? 0)];
    }

    /** Los 5 productos con más unidades vendidas; los regalos (a Bs 0) no cuentan. */
    private static function masVendidos(DateTimeImmutable $desde, DateTimeImmutable $hasta): array
    {
        return DB::filas(
            "SELECT d.producto_id, MAX(d.descripcion) AS nombre, SUM(d.cantidad) AS unidades, SUM(d.subtotal) AS monto
             FROM detalle_venta d JOIN ventas v ON v.id = d.venta_id
             WHERE v.estado = 'cobrada' AND v.creado_en >= ? AND v.creado_en < ? AND d.subtotal > 0
             GROUP BY d.producto_id ORDER BY unidades DESC, monto DESC, nombre LIMIT 5",
            [Reloj::sql($desde), Reloj::sql($hasta)],
        );
    }

    /** Pagos QR que esperan la confirmación del administrador (HU-39 y HU-41 los generan). */
    private static function qrPendientes(): array
    {
        $f = DB::fila(
            "SELECT COUNT(*) AS cantidad, COALESCE(SUM(p.monto), 0) AS monto FROM pagos p JOIN ventas v ON v.id = p.venta_id
             WHERE p.metodo = 'qr' AND p.estado = 'pendiente' AND v.estado = 'cobrada'"
        ) ?? [];
        return ['cantidad' => (int) ($f['cantidad'] ?? 0), 'monto' => (float) ($f['monto'] ?? 0)];
    }

    /** La caja abierta con lo vendido en su turno, o null. */
    private static function caja(): ?array
    {
        $caja = Caja::abierta();
        if ($caja === null) {
            return null;
        }
        $f = DB::fila("SELECT COUNT(*) AS cantidad, COALESCE(SUM(total), 0) AS total FROM ventas WHERE caja_id = ? AND estado = 'cobrada'", [(int) $caja['id']]) ?? [];
        return $caja + ['ventas' => (int) ($f['cantidad'] ?? 0), 'vendido' => (float) ($f['total'] ?? 0)];
    }

    /**
     * Lo que el administrador debería atender, de lo más urgente a lo menos.
     * @return list<array{tono: string, icono: string, titulo: string, detalle: string, enlace: string, accion: string}>
     */
    private static function avisos(DateTimeImmutable $ahora): array
    {
        $avisos = [];

        $caja = Caja::abierta();
        if ($caja !== null && $caja['fecha'] < $ahora->format('Y-m-d')) {
            $avisos[] = ['tono' => 'peligro', 'icono' => 'caja', 'titulo' => 'La caja de ' . mb_strtolower($caja['turno']) . ' del ' . (new DateTimeImmutable($caja['fecha']))->format('d/m') . ' sigue abierta',
                'detalle' => 'Hay que contar y cerrar esa caja; hasta entonces no se abre la de hoy.', 'enlace' => '/caja', 'accion' => 'Ir a caja'];
        }

        $fiscal = DB::fila('SELECT * FROM parametros_fiscales WHERE id = 1');
        if ($fiscal !== null && !ParametrosFiscales::cufdVigente($fiscal)) {
            $avisos[] = ['tono' => 'aviso', 'icono' => 'alerta', 'titulo' => 'El CUFD no corresponde a hoy',
                'detalle' => 'Actualiza los parámetros fiscales antes de facturar.', 'enlace' => '/config#fiscal', 'accion' => 'Revisar'];
        }

        foreach (DB::filas('SELECT nombre, bloqueado_hasta FROM usuarios WHERE activo = 1 AND bloqueado_hasta > ? ORDER BY nombre', [Reloj::sql($ahora)]) as $u) {
            $avisos[] = ['tono' => 'aviso', 'icono' => 'candado', 'titulo' => $u['nombre'] . ' está bloqueado hasta las ' . substr($u['bloqueado_hasta'], 11, 5),
                'detalle' => '5 intentos fallidos de ingreso.', 'enlace' => '/usuarios', 'accion' => 'Revisar'];
        }

        $agotados = DB::filas('SELECT nombre FROM productos WHERE activo = 1 AND agotado = 1 ORDER BY nombre');
        if ($agotados) {
            $nombres = array_column($agotados, 'nombre');
            $avisos[] = ['tono' => 'neutra', 'icono' => 'agotado',
                'titulo' => count($nombres) === 1 ? $nombres[0] . ' está agotado' : count($nombres) . ' productos agotados',
                'detalle' => count($nombres) === 1 ? 'Se ve en el punto de venta, pero no se puede vender.' : implode(', ', array_slice($nombres, 0, 4)) . (count($nombres) > 4 ? '…' : '.'),
                'enlace' => '/catalogo/productos', 'accion' => 'Ver'];
        }
        return $avisos;
    }
}
