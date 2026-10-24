<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Models\Bitacora;
use App\Models\ConfiguracionTickets;
use DomainException;

/** HU-56: regenera un ticket guardado y audita el PDF generado, sin emitir otra factura. */
final class ReimpresionService
{
    /** Al abrir el modo de reimpresión se regeneran y auditan los tres tickets visibles. */
    public static function registrarVista(array $factura): void
    {
        if ($factura['estado'] !== 'emitida' || DB::valor('SELECT estado FROM ventas WHERE id=?', [$factura['venta_id']]) !== 'cobrada') {
            throw new DomainException('Solo se pueden reimprimir tickets de una venta cobrada y no anulada.');
        }
        $perfiles = ConfiguracionTickets::todas();
        DB::transaccion(static function () use ($factura, $perfiles): void {
            foreach (ConfiguracionTickets::TIPOS as $tipo => $nombre) {
                $destino = $tipo === 'comanda' ? 'cocina' : 'caja';
                Bitacora::registrar('factura.reimpresion', 'facturas', $factura['id'], [
                    'tipo' => $tipo, 'ticket' => $nombre, 'destino' => $destino,
                    'factura_numero' => (int) $factura['numero'], 'venta_id' => (int) $factura['venta_id'],
                    'copias' => 1, 'medio' => 'vista', 'accion' => 'generacion', 'ancho' => $perfiles[$destino]['ancho'],
                ]);
            }
        });
    }

    public static function generar(array $factura, mixed $tipo): array
    {
        if (!is_string($tipo) || !isset(ConfiguracionTickets::TIPOS[$tipo])) {
            throw new DomainException('Elige Comanda, Copia de caja o Factura.');
        }
        $destino = $tipo === 'comanda' ? 'cocina' : 'caja';
        $perfil = ConfiguracionTickets::todas()[$destino] ?? throw new DomainException('No se encontró el perfil del ticket.');
        // La selección explícita recupera cualquiera de los tres tickets, aunque no esté en el lote habitual.
        $pdf = self::generarLote($factura, ['perfil' => $perfil, 'tipos' => [$tipo]]);
        $pdf['nombre'] = 'Reimpresion-' . $tipo . '-factura-' . (int) $factura['numero'] . '.pdf';
        return $pdf;
    }

    /** Las direcciones PDF anteriores pasan por el mismo control y auditan cada tipo recuperado. */
    public static function generarLote(array $factura, array $salida): array
    {
        $estadoVenta = DB::valor('SELECT estado FROM ventas WHERE id = ?', [$factura['venta_id']]);
        if ($estadoVenta !== 'cobrada' || $factura['estado'] !== 'emitida') {
            throw new DomainException('Solo se pueden reimprimir tickets de una venta cobrada y no anulada.');
        }
        $salida['reimpresion'] = true;
        $bytes = TicketsPdf::generar($factura['snapshot'], $salida);
        DB::transaccion(static function () use ($factura, $salida): void {
            foreach ($salida['tipos'] as $tipo) {
                $perfilTicket = isset($salida['perfiles']) ? $salida['perfiles'][$tipo === 'comanda' ? 'cocina' : 'caja'] : $salida['perfil'];
                Bitacora::registrar('factura.reimpresion', 'facturas', $factura['id'], [
                    'tipo' => $tipo, 'ticket' => ConfiguracionTickets::TIPOS[$tipo], 'destino' => $perfilTicket['destino'],
                    'factura_numero' => (int) $factura['numero'], 'venta_id' => (int) $factura['venta_id'],
                    'copias' => $perfilTicket['copias'], 'medio' => 'pdf', 'accion' => 'generacion',
                ]);
            }
        });
        return ['bytes' => $bytes, 'nombre' => 'Reimpresion-' . ($salida['seleccion'] ?? $salida['perfil']['destino']) . '-factura-' . (int) $factura['numero'] . '.pdf'];
    }
}
