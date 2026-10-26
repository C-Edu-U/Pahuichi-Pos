<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Auth;
use App\Core\Sesion;
use App\Models\Bitacora;
use App\Models\ConfiguracionTickets;
use DomainException;

/** HU-56: regenera un ticket guardado y audita el PDF generado, sin emitir otra factura. */
final class ReimpresionService
{
    /** Una entrada por transición de pantalla, independiente de la generación del PDF. */
    public static function cambiarVista(array $factura, bool $reimpresion): void
    {
        $vista = ['usuario_id' => Auth::id(), 'factura_id' => (int) $factura['id'], 'reimpresion' => $reimpresion];
        if ($reimpresion && Sesion::get('tickets_vista') !== $vista) {
            Bitacora::registrar('factura.entrada_reimpresion', 'facturas', $factura['id'], [
                'factura_numero' => (int) $factura['numero'], 'venta_id' => (int) $factura['venta_id'],
                'medio' => 'vista', 'accion' => 'entrada',
            ]);
        }
        // Solo recordar el modo una vez que su registro se guardó correctamente.
        Sesion::set('tickets_vista', $vista);
    }

    /** La vista solo valida el estado; la auditoría corresponde al PDF solicitado. */
    public static function validar(array $factura): void
    {
        if ($factura['estado'] !== 'emitida' || DB::valor('SELECT estado FROM ventas WHERE id=?', [$factura['venta_id']]) !== 'cobrada') {
            throw new DomainException('Solo se pueden reimprimir tickets de una venta cobrada y no anulada.');
        }
    }

    public static function generar(array $factura, mixed $tipo, bool $descargar = false): array
    {
        if (!is_string($tipo) || !isset(ConfiguracionTickets::TIPOS[$tipo])) {
            throw new DomainException('Elige Comanda, Copia de caja o Factura.');
        }
        $destino = $tipo === 'comanda' ? 'cocina' : 'caja';
        $perfil = ConfiguracionTickets::todas()[$destino] ?? throw new DomainException('No se encontró el perfil del ticket.');
        // La selección explícita recupera cualquiera de los tres tickets, aunque no esté en el lote habitual.
        $pdf = self::generarLote($factura, ['perfil' => $perfil, 'tipos' => [$tipo], 'descargar' => $descargar]);
        $pdf['nombre'] = 'Reimpresion-' . $tipo . '-factura-' . (int) $factura['numero'] . '.pdf';
        return $pdf;
    }

    /** Las direcciones PDF anteriores pasan por el mismo control y auditan cada tipo recuperado. */
    public static function generarLote(array $factura, array $salida): array
    {
        self::validar($factura);
        $salida['reimpresion'] = true;
        $bytes = TicketsPdf::generar($factura['snapshot'], $salida);
        DB::transaccion(static function () use ($factura, $salida): void {
            foreach ($salida['tipos'] as $tipo) {
                $perfilTicket = isset($salida['perfiles']) ? $salida['perfiles'][$tipo === 'comanda' ? 'cocina' : 'caja'] : $salida['perfil'];
                Bitacora::registrar('factura.reimpresion', 'facturas', $factura['id'], [
                    'tipo' => $tipo, 'ticket' => ConfiguracionTickets::TIPOS[$tipo], 'destino' => $perfilTicket['destino'],
                    'factura_numero' => (int) $factura['numero'], 'venta_id' => (int) $factura['venta_id'],
                    'copias' => $perfilTicket['copias'], 'medio' => 'pdf', 'accion' => 'generacion',
                    'operacion' => !empty($salida['descargar']) ? 'descargar' : 'ver',
                ]);
            }
        });
        return ['bytes' => $bytes, 'nombre' => 'Reimpresion-' . ($salida['seleccion'] ?? $salida['perfil']['destino']) . '-factura-' . (int) $factura['numero'] . '.pdf'];
    }
}
