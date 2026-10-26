<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Reloj;
use App\Models\Arqueo;
use App\Models\Bitacora;
use App\Models\Caja;
use App\Models\Configuracion;
use App\Models\MovimientoCaja;
use App\Models\Venta;
use DomainException;

/**
 * Arqueo automático y resumen de cierre (HU-24).
 *
 * Efectivo esperado = fondo inicial + ingresos del turno - egresos del turno
 * + ventas cobradas en efectivo. Las ventas por QR pendientes o rechazadas no suman.
 *
 * El cierre bloquea la fila de la caja, como el cobro y los egresos: una venta que llega
 * mientras se confirma espera, o entra antes y ya cuenta en el esperado que se guarda.
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

    /** Umbral configurable: a partir de esta diferencia la observación es obligatoria (HU-26). */
    public static function umbralObservacion(): float
    {
        return Configuracion::umbralObservacion();
    }

    /** La diferencia es igual o mayor al umbral: el cajero tiene que explicar. */
    public static function exigeObservacion(float $diferencia): bool
    {
        return abs($diferencia) >= self::umbralObservacion();
    }

    /** Lo que se le pide al cajero cuando falta la observación. */
    public static function pedidoObservacion(float $diferencia): string
    {
        return 'La diferencia es de Bs ' . number_format(abs($diferencia), 2, ',', '.')
            . '. Escribe una observación para explicar el ' . ($diferencia < 0 ? 'faltante' : 'sobrante') . '.';
    }

    /** Resumen del turno: ventas, medios de pago, QR, descuentos y anulaciones. */
    public static function resumen(int $cajaId): array
    {
        $resumen = Venta::resumen($cajaId);
        $movimientos = MovimientoCaja::totales($cajaId);
        $resumen['mov_ingresos'] = number_format((float) $movimientos['ingresos'], 2, '.', '');
        $resumen['mov_egresos'] = number_format((float) $movimientos['egresos'], 2, '.', '');
        return $resumen;
    }

    /**
     * Confirma el cierre: con la caja bloqueada, calcula el esperado, la diferencia y el
     * resumen, los guarda y cierra la caja. Devuelve el esperado y la diferencia guardados.
     *
     * @return array{esperado: float, diferencia: float}
     * @throws DomainException si la caja ya está cerrada, no tiene conteo o falta la observación
     */
    public function confirmarCierre(int $cajaId, string $observacion = ''): array
    {
        return DB::transaccion(static function () use ($cajaId, $observacion): array {
            $caja = DB::fila('SELECT * FROM cajas WHERE id = ? FOR UPDATE', [$cajaId]);
            if ($caja === null || $caja['estado'] !== 'abierta') {
                throw new DomainException('La caja ya está cerrada.');
            }
            $conteo = Arqueo::porCaja($cajaId);
            if ($conteo === null) {
                throw new DomainException('Cuenta el efectivo antes de cerrar la caja.');
            }

            $esperado = self::esperado($caja);
            $diferencia = self::diferencia((float) $conteo['total_contado'], $esperado);
            // Se vuelve a mirar aquí: una venta de último momento puede cambiar la diferencia
            if ($observacion === '' && self::exigeObservacion($diferencia)) {
                throw new DomainException(self::pedidoObservacion($diferencia));
            }
            $resumen = self::resumen($cajaId);

            $cerradoEn = Reloj::sql();
            Arqueo::guardarCierre($cajaId, $esperado, $diferencia, $resumen, $observacion, $cerradoEn);
            Caja::cerrar($cajaId, $cerradoEn);

            Bitacora::registrar('caja.arqueo', 'cajas', $cajaId, [
                'efectivo_esperado' => number_format($esperado, 2, '.', ''),
                'total_contado' => number_format((float) $conteo['total_contado'], 2, '.', ''),
                'diferencia' => number_format($diferencia, 2, '.', ''),
                'ventas' => $resumen['ventas'] ?? 0,
            ]);

            return ['esperado' => $esperado, 'diferencia' => $diferencia];
        });
    }
}
