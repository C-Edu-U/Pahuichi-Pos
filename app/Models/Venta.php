<?php

declare(strict_types=1);
namespace App\Models;

use App\Core\DB;

/**
 * Ventas. El cobro (HU-57) las crea con su detalle y su pago; el arqueo de caja
 * (HU-24) las lee para el efectivo esperado y el resumen de cierre.
 * Esquema: ventas (estado cobrada o anulada) y pagos aparte (método y estado de cada pago).
 */
final class Venta
{
    public static function porUuid(string $uuid): ?array
    {
        return DB::fila('SELECT * FROM ventas WHERE uuid = ?', [$uuid]);
    }

    public static function cajaParaCobrar(int $id): ?array
    {
        return DB::fila('SELECT * FROM cajas WHERE id = ? FOR UPDATE', [$id]);
    }

    public static function productosParaCobrar(array $ids): array
    {
        $marcas = implode(',', array_fill(0, count($ids), '?'));
        return DB::filas('SELECT p.*, c.activo AS categoria_activa FROM productos p JOIN categorias c ON c.id = p.categoria_id WHERE p.id IN (' . $marcas . ') ORDER BY p.id LOCK IN SHARE MODE', $ids);
    }

    public static function crear(array $d): int
    {
        return DB::insertar('INSERT INTO ventas (uuid, solicitud_hash, caja_id, usuario_id, tipo_pedido, mesa, subtotal, total, creado_en) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', array_values($d));
    }

    public static function detalle(int $id, array $l): void
    {
        DB::insertar('INSERT INTO detalle_venta (venta_id, producto_id, descripcion, cantidad, precio_unitario, subtotal, nota) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$id, $l['producto_id'], $l['nombre'], $l['cantidad'], $l['precio'], $l['subtotal'], $l['nota'] ?: null]);
    }

    public static function pago(int $id, string $total, string $recibido, string $cambio): void
    {
        DB::insertar("INSERT INTO pagos (venta_id, metodo, monto, recibido, cambio) VALUES (?, 'efectivo', ?, ?, ?)", [$id, $total, $recibido, $cambio]);
    }

    /**
     * Efectivo cobrado en la caja: pagos en efectivo confirmados de ventas no anuladas.
     * Los QR pendientes o rechazados no suman al efectivo esperado (HU-24).
     */
    public static function efectivoCobrado(int $cajaId): float
    {
        return (float) (DB::valor(
            "SELECT COALESCE(SUM(p.monto), 0)
             FROM pagos p JOIN ventas v ON v.id = p.venta_id
             WHERE v.caja_id = ? AND v.estado = 'cobrada' AND p.metodo = 'efectivo' AND p.estado = 'confirmado'",
            [$cajaId],
        ) ?? 0);
    }

    /** Resumen del turno: ventas, medios de pago, QR, descuentos y anulaciones (HU-24). */
    public static function resumen(int $cajaId): array
    {
        $ventas = DB::fila(
            "SELECT COUNT(*) AS ventas,
                    COALESCE(SUM(CASE WHEN estado = 'cobrada' THEN total END), 0) AS total,
                    COALESCE(SUM(CASE WHEN estado = 'cobrada' THEN descuento END), 0) AS descuentos,
                    COALESCE(SUM(CASE WHEN estado = 'anulada' THEN total END), 0) AS anuladas
             FROM ventas WHERE caja_id = ?",
            [$cajaId],
        ) ?? [];
        $pagos = DB::fila(
            "SELECT
                COALESCE(SUM(CASE WHEN p.metodo = 'efectivo' AND p.estado = 'confirmado' THEN p.monto END), 0) AS efectivo,
                COALESCE(SUM(CASE WHEN p.metodo = 'qr' AND p.estado = 'confirmado' THEN p.monto END), 0) AS qr_confirmado,
                COALESCE(SUM(CASE WHEN p.metodo = 'qr' AND p.estado = 'pendiente' THEN p.monto END), 0) AS qr_pendiente,
                COALESCE(SUM(CASE WHEN p.metodo = 'qr' AND p.estado = 'rechazado' THEN p.monto END), 0) AS qr_rechazado
             FROM pagos p JOIN ventas v ON v.id = p.venta_id
             WHERE v.caja_id = ? AND v.estado = 'cobrada'",
            [$cajaId],
        ) ?? [];

        $efectivo = (float) ($pagos['efectivo'] ?? 0);
        $qrConfirmado = (float) ($pagos['qr_confirmado'] ?? 0);
        $qrPendiente = (float) ($pagos['qr_pendiente'] ?? 0);
        $qrRechazado = (float) ($pagos['qr_rechazado'] ?? 0);

        $resumen = [
            'ventas' => (int) ($ventas['ventas'] ?? 0),
            'total' => self::monto((float) ($ventas['total'] ?? 0)),
            'efectivo' => self::monto($efectivo),
            'qr_confirmado' => self::monto($qrConfirmado),
            'qr_pendiente' => self::monto($qrPendiente),
            'qr_rechazado' => self::monto($qrRechazado),
            // El ERS solo cobra en efectivo, QR o mixto: tarjeta y otro quedan en cero
            'tarjeta' => self::monto(0),
            'otro' => self::monto(0),
            'descuentos' => self::monto((float) ($ventas['descuentos'] ?? 0)),
            'anuladas' => self::monto((float) ($ventas['anuladas'] ?? 0)),
        ];

        $resumen['por_metodo'] = [
            'Efectivo' => $resumen['efectivo'],
            'QR' => self::monto($qrConfirmado + $qrPendiente + $qrRechazado),
            'Tarjeta' => $resumen['tarjeta'],
            'Otro' => $resumen['otro'],
        ];
        $resumen['qr'] = [
            'confirmados' => $resumen['qr_confirmado'],
            'pendientes' => $resumen['qr_pendiente'],
            'rechazados' => $resumen['qr_rechazado'],
        ];

        return $resumen;
    }

    /** Monto con dos decimales y en texto: el resumen queda guardado sin perder centavos. */
    private static function monto(float $valor): string
    {
        return number_format($valor, 2, '.', '');
    }

    public static function deCaja(int $cajaId): array
    {
        return DB::filas(
            'SELECT * FROM ventas WHERE caja_id = ? ORDER BY creado_en DESC, id DESC',
            [$cajaId],
        );
    }
}
