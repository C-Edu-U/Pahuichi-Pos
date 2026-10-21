<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

/** Ingresos y egresos de efectivo de una caja (HU-22). */
final class MovimientoCaja
{
    public static function porCaja(int $cajaId): array
    {
        return DB::filas(
            'SELECT m.*, u.nombre AS usuario
             FROM movimientos_caja m
             JOIN usuarios u ON u.id = m.usuario_id
             WHERE m.caja_id = ?
             ORDER BY m.creado_en DESC, m.id DESC',
            [$cajaId],
        );
    }

    public static function crear(int $cajaId, string $tipo, float $monto, string $motivo, int $usuarioId, string $creadoEn): int
    {
        return DB::insertar(
            'INSERT INTO movimientos_caja (caja_id, tipo, monto, motivo, usuario_id, creado_en) VALUES (?, ?, ?, ?, ?, ?)',
            [$cajaId, $tipo, $monto, $motivo, $usuarioId, $creadoEn],
        );
    }

    /** Totales del turno: lo que entró y lo que salió. */
    public static function totales(int $cajaId): array
    {
        $fila = DB::fila(
            "SELECT
                COALESCE(SUM(CASE WHEN tipo = 'ingreso' THEN monto END), 0) AS ingresos,
                COALESCE(SUM(CASE WHEN tipo = 'egreso' THEN monto END), 0) AS egresos
             FROM movimientos_caja WHERE caja_id = ?",
            [$cajaId],
        ) ?? ['ingresos' => 0, 'egresos' => 0];
        $fila['ingresos'] = (float) $fila['ingresos'];
        $fila['egresos'] = (float) $fila['egresos'];
        $fila['neto'] = $fila['ingresos'] - $fila['egresos'];
        return $fila;
    }
}
