<?php

declare(strict_types=1);
namespace App\Services;

use App\Core\DB;
use App\Models\Bitacora;
use DomainException;

/** Compatibilidad de nombre: consulta de documentos emitidos, sin permisos de entrega temporales. */
final class EntregaInicialService
{
    public static function generar(array $factura, mixed $destino, string $token = ''): array
    {
        if (!is_string($destino)) throw new DomainException('Elige Caja o Cocina.');
        return self::generarDocumento($factura, TicketsPdf::preparar($destino));
    }

    public static function generarDocumento(array $factura, array $salida): array
    {
        if ($factura['estado'] !== 'emitida' || DB::valor('SELECT estado FROM ventas WHERE id=?', [$factura['venta_id']]) !== 'cobrada') {
            throw new DomainException('La venta debe estar cobrada y no anulada.');
        }
        $salida['reimpresion'] = false;
        $bytes = TicketsPdf::generar($factura['snapshot'], $salida);
        Bitacora::registrar('factura.consulta_original', 'facturas', $factura['id'], [
            'numero' => (int) $factura['numero'], 'venta_id' => (int) $factura['venta_id'],
            'tipos' => $salida['tipos'], 'destino' => isset($salida['seleccion']) ? 'seleccion:' . $salida['seleccion'] : $salida['perfil']['destino'], 'medio' => 'pdf',
        ]);
        return ['bytes' => $bytes, 'nombre' => 'Tickets-' . ($salida['seleccion'] ?? $salida['perfil']['destino']) . '-factura-' . (int) $factura['numero'] . '.pdf'];
    }
}
