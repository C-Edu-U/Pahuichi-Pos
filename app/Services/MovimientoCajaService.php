<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Reloj;
use App\Models\Bitacora;
use App\Models\MovimientoCaja;
use DomainException;

/**
 * Ingresos y egresos de efectivo durante el turno (HU-22).
 * El motivo es obligatorio: sin él el arqueo no se puede explicar.
 * Un egreso no puede superar el efectivo que debería haber en caja en ese momento
 * (fondo + ventas en efectivo + ingresos − egresos). Al cajero no se le dice cuánto
 * hay, porque el cierre es ciego; al administrador, sí.
 */
final class MovimientoCajaService
{
    public const TIPOS = ['ingreso', 'egreso'];

    /**
     * @throws DomainException si el motivo falta o el monto no es válido
     */
    public function registrar(int $cajaId, string $tipo, float $monto, string $motivo, array $usuario): int
    {
        if (!in_array($tipo, self::TIPOS, true)) {
            throw new DomainException('Elige si es un ingreso o un egreso.');
        }
        if ($monto <= 0) {
            throw new DomainException('El monto debe ser mayor a cero.');
        }
        if ($monto > 99999.99) {
            throw new DomainException('El monto va de Bs 0,01 a Bs 99.999,99.');
        }
        $motivo = trim($motivo);
        if (mb_strlen($motivo) < 3) {
            throw new DomainException('Escribe el motivo del movimiento.');
        }
        if (mb_strlen($motivo) > 200) {
            throw new DomainException('El motivo tiene hasta 200 caracteres.');
        }

        // La fila de la caja queda bloqueada, como en el cobro: una venta simultánea no se cuela
        return DB::transaccion(static function () use ($cajaId, $tipo, $monto, $motivo, $usuario): int {
            $caja = DB::fila('SELECT * FROM cajas WHERE id = ? FOR UPDATE', [$cajaId]);
            if ($caja === null || $caja['estado'] !== 'abierta') {
                throw new DomainException('La caja ya no está abierta.');
            }
            if ($tipo === 'egreso') {
                $disponible = ArqueoService::esperado($caja);
                if ((int) round($monto * 100) > (int) round($disponible * 100)) {
                    throw new DomainException($usuario['rol'] === 'administrador'
                        ? 'El egreso supera el efectivo que debería haber en caja: Bs ' . number_format(max(0, $disponible), 2, ',', '.') . '.'
                        : 'El egreso supera el efectivo que debería haber en caja. Revisa el monto.');
                }
            }

            $id = MovimientoCaja::crear($cajaId, $tipo, $monto, $motivo, (int) $usuario['id'], Reloj::sql());
            Bitacora::registrar('caja.' . $tipo, 'cajas', $cajaId, [
                'monto' => number_format($monto, 2, '.', ''),
                'motivo' => $motivo,
            ]);
            return $id;
        });
    }
}
