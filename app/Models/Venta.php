<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

/** Ventas de una caja. El arqueo (HU-24) las lee para el efectivo esperado y el resumen. */
final class Venta
{
    /**
     * Efectivo cobrado por ventas: solo las pagadas y no anuladas.
     * Las de QR pendientes o rechazadas no suman al efectivo esperado.
     */
    public static function efectivoCobrado(int $cajaId): float
    {
        return (float) (DB::valor(
            "SELECT COALESCE(SUM(total), 0) FROM ventas
             WHERE caja_id = ? AND metodo_pago = 'efectivo' AND estado_pago = 'pagado' AND anulada = 0",
            [$cajaId],
        ) ?? 0);
    }

    /** Resumen del turno: ventas, medios de pago, QR, descuentos y anulaciones. */
    public static function resumen(int $cajaId): array
    {
        $fila = DB::fila(
            "SELECT
                COUNT(*) AS ventas,
                COALESCE(SUM(CASE WHEN anulada = 0 THEN total END), 0) AS total,
                COALESCE(SUM(CASE WHEN anulada = 0 AND metodo_pago = 'efectivo' AND estado_pago = 'pagado' THEN total END), 0) AS efectivo,
                COALESCE(SUM(CASE WHEN anulada = 0 AND metodo_pago = 'qr' AND estado_pago = 'pagado' THEN total END), 0) AS qr_confirmado,
                COALESCE(SUM(CASE WHEN anulada = 0 AND metodo_pago = 'qr' AND estado_pago = 'pendiente' THEN total END), 0) AS qr_pendiente,
                COALESCE(SUM(CASE WHEN anulada = 0 AND metodo_pago = 'qr' AND estado_pago = 'rechazado' THEN total END), 0) AS qr_rechazado,
                COALESCE(SUM(CASE WHEN anulada = 0 AND metodo_pago = 'tarjeta' AND estado_pago = 'pagado' THEN total END), 0) AS tarjeta,
                COALESCE(SUM(CASE WHEN anulada = 0 AND metodo_pago = 'otro' AND estado_pago = 'pagado' THEN total END), 0) AS otro,
                COALESCE(SUM(CASE WHEN anulada = 0 THEN descuento END), 0) AS descuentos,
                COALESCE(SUM(CASE WHEN anulada = 1 THEN total END), 0) AS anuladas
             FROM ventas WHERE caja_id = ?",
            [$cajaId],
        ) ?? [];

        $efectivo = (float) ($fila['efectivo'] ?? 0);
        $qrConfirmado = (float) ($fila['qr_confirmado'] ?? 0);
        $qrPendiente = (float) ($fila['qr_pendiente'] ?? 0);
        $qrRechazado = (float) ($fila['qr_rechazado'] ?? 0);
        $tarjeta = (float) ($fila['tarjeta'] ?? 0);
        $otro = (float) ($fila['otro'] ?? 0);

        $resumen = [
            'ventas' => (int) ($fila['ventas'] ?? 0),
            'total' => self::monto((float) ($fila['total'] ?? 0)),
            'efectivo' => self::monto($efectivo),
            'qr_confirmado' => self::monto($qrConfirmado),
            'qr_pendiente' => self::monto($qrPendiente),
            'qr_rechazado' => self::monto($qrRechazado),
            'tarjeta' => self::monto($tarjeta),
            'otro' => self::monto($otro),
            'descuentos' => self::monto((float) ($fila['descuentos'] ?? 0)),
            'anuladas' => self::monto((float) ($fila['anuladas'] ?? 0)),
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
            'SELECT * FROM ventas WHERE caja_id = ? ORDER BY fecha DESC, id DESC',
            [$cajaId],
        );
    }
}
