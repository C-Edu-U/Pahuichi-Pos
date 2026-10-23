<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

/** Configuración editable desde la pantalla de Configuración (HU-26). */
final class Configuracion
{
    public const ORIGINALES_CAJEROS = 'tickets_originales_cajeros';

    /** Umbral de la observación obligatoria al cerrar la caja. */
    public const UMBRAL_OBSERVACION = 'umbral_observacion_caja';

    public static function valor(string $clave, string $defecto = ''): string
    {
        $valor = DB::valor('SELECT valor FROM configuracion WHERE clave = ?', [$clave]);
        return $valor === null ? $defecto : (string) $valor;
    }

    public static function guardar(string $clave, string $valor): void
    {
        DB::consulta(
            'INSERT INTO configuracion (clave, valor) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor)',
            [$clave, $valor],
        );
    }

    /** Umbral en bolivianos. Por defecto Bs 10,00 si nunca se configuró. */
    public static function umbralObservacion(): float
    {
        $valor = self::valor(self::UMBRAL_OBSERVACION, '10.00');
        return is_numeric($valor) ? round((float) $valor, 2) : 10.00;
    }
}
