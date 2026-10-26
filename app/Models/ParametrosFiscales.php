<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use App\Core\Reloj;
use DateTimeImmutable;

/**
 * Parámetros fiscales simulados (HU-09): una sola fila, id = 1.
 * No son credenciales reales del SIN: sirven para armar la factura de práctica.
 */
final class ParametrosFiscales
{
    public const MODALIDADES = [
        'computarizada' => 'Computarizada en línea',
        'electronica' => 'Electrónica en línea',
    ];

    public static function obtener(): array
    {
        return DB::fila('SELECT * FROM parametros_fiscales WHERE id = 1') ?? [];
    }

    public static function guardar(array $d): void
    {
        DB::consulta(
            'UPDATE parametros_fiscales SET codigo_sucursal = ?, codigo_punto_venta = ?, actividad_economica = ?, cuis = ?,
             cufd = ?, cufd_codigo_control = ?, cufd_vigencia = ?, numero_inicial = ?, modalidad = ? WHERE id = 1',
            [
                $d['codigo_sucursal'], $d['codigo_punto_venta'], $d['actividad_economica'], $d['cuis'],
                $d['cufd'], $d['cufd_codigo_control'], $d['cufd_vigencia'], $d['numero_inicial'], $d['modalidad'],
            ],
        );
    }

    /** El CUFD vale solo el día de su vigencia y hasta la hora indicada (RF-65). */
    public static function cufdVigente(array $p): bool
    {
        $vigencia = new DateTimeImmutable($p['cufd_vigencia']);
        $ahora = Reloj::ahora();
        return $vigencia >= $ahora && $vigencia->format('Y-m-d') === $ahora->format('Y-m-d');
    }
}
