<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

/** Datos del negocio (HU-08): una sola fila, id = 1. */
final class Negocio
{
    public const CAMPOS = ['razon_social', 'nombre_comercial', 'nit', 'direccion', 'telefono', 'municipio'];

    public static function obtener(): array
    {
        return DB::fila('SELECT * FROM negocio WHERE id = 1') ?? [];
    }

    public static function guardar(array $datos): void
    {
        DB::consulta(
            'UPDATE negocio SET razon_social = ?, nombre_comercial = ?, nit = ?, direccion = ?, telefono = ?, municipio = ? WHERE id = 1',
            [$datos['razon_social'], $datos['nombre_comercial'], $datos['nit'], $datos['direccion'], $datos['telefono'] ?: null, $datos['municipio']],
        );
    }

    public static function cambiarLogo(?string $archivo): void
    {
        DB::consulta('UPDATE negocio SET logo = ? WHERE id = 1', [$archivo]);
    }
}
