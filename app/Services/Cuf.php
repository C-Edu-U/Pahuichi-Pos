<?php

declare(strict_types=1);
namespace App\Services;

use DateTimeImmutable;

/** HU-57: CUF académico de 28 hexadecimales; no es integración con el SIN.
 * Sigue el hash SHA-256 del demo, con campos delimitados para evitar ambigüedad.
 * La restricción UNIQUE de facturas.cuf es la garantía final de unicidad.
 */
final class Cuf
{
    public static function generar(array $negocio, array $fiscal, int $numero, DateTimeImmutable $fecha): string
    {
        $base = json_encode([$negocio['nit'], $fecha->format('YmdHis.u'),
            (int) $fiscal['codigo_sucursal'], (int) $fiscal['codigo_punto_venta'],
            $numero, $fiscal['cufd_codigo_control']], JSON_THROW_ON_ERROR);
        return strtoupper(substr(hash('sha256', $base), 0, 28));
    }
}
