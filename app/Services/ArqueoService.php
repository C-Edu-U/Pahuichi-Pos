<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Reloj;
use App\Models\Arqueo;
use App\Models\Bitacora;
use App\Models\Caja;
use App\Models\MovimientoCaja;
use App\Models\Venta;
use DomainException;

/**
 * Arqueo automático y resumen de cierre (HU-24).
 *
 * Efectivo esperado = fondo inicial + ingresos del turno - egresos del turno
 * + ventas cobradas en efectivo. Las ventas por QR pendientes o rechazadas no suman.
 */
final class ArqueoService
{
    /** Efectivo que debería haber en la caja al cerrar. */
    public static function esperado(array $caja): float
    {
        $cajaId = (int) $caja['id'];
        $movimientos = MovimientoCaja::totales($cajaId);
        $efectivo = Venta::efectivoCobrado($cajaId);

        return round((float) $caja['fondo_inicial'] + $movimientos['ingresos'] - $movimientos['egresos'] + $efectivo, 2);
    }

    /** Diferencia entre lo contado y lo esperado. Negativa = faltante. */
    public static function diferencia(float $totalContado, float $esperado): float
    {
        return round($totalContado - $esperado, 2);
    }

    /** Resumen del turno: ventas, medios de pago, QR, descuentos y anulaciones. */
    public static function resumen(int $cajaId): array
    {
        $resumen = Venta::resumen($cajaId);
        $movimientos = MovimientoCaja::totales($cajaId);
        $resumen['mov_ingresos'] = $movimientos['ingresos'];
        $resumen['mov_egresos'] = $movimientos['egresos'];
        return $resumen;
    }

    /**
     * Confirma el cierre: guarda el esperado, la diferencia y el resumen, y cierra la caja.
     *
     * @throws DomainException si la caja no tiene conteo
     */
    public function confirmarCierre(int $cajaId, float $esperado, float $diferencia, array $resumen, string $observacion = ''): void
    {
        $conteo = Arqueo::porCaja($cajaId);
        if ($conteo === null) {
            throw new DomainException('Cuenta el efectivo antes de cerrar la caja.');
        }

        $cerradoEn = Reloj::sql();
        Arqueo::guardarCierre($cajaId, $esperado, $diferencia, $resumen, $observacion, $cerradoEn);
        Caja::cerrar($cajaId, $cerradoEn);

        Bitacora::registrar('caja.arqueo', 'cajas', $cajaId, [
            'efectivo_esperado' => number_format($esperado, 2, '.', ''),
            'total_contado' => number_format((float) $conteo['total_contado'], 2, '.', ''),
            'diferencia' => number_format($diferencia, 2, '.', ''),
            'ventas' => $resumen['ventas'] ?? 0,
        ]);
    }
}
