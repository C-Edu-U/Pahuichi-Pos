<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use App\Core\Reloj;
use DateTimeImmutable;

/**
 * Intentos fallidos que no se pueden atribuir a un usuario: el PIN (no dice de quién es)
 * y los usuarios que no existen. Se cuentan por equipo (dirección IP).
 */
final class IntentoAcceso
{
    public static function bloqueadoHasta(string $clave): ?DateTimeImmutable
    {
        $hasta = DB::valor('SELECT bloqueado_hasta FROM intentos_acceso WHERE clave = ?', [$clave]);
        if ($hasta === null) {
            return null;
        }
        $hasta = new DateTimeImmutable($hasta);
        return $hasta > Reloj::ahora() ? $hasta : null;
    }

    public static function sumar(string $clave): int
    {
        DB::consulta(
            'INSERT INTO intentos_acceso (clave, intentos) VALUES (?, 1)
             ON DUPLICATE KEY UPDATE intentos = intentos + 1, bloqueado_hasta = NULL',
            [$clave],
        );
        return (int) DB::valor('SELECT intentos FROM intentos_acceso WHERE clave = ?', [$clave]);
    }

    public static function bloquear(string $clave, DateTimeImmutable $hasta): void
    {
        DB::consulta('UPDATE intentos_acceso SET intentos = 0, bloqueado_hasta = ? WHERE clave = ?', [Reloj::sql($hasta), $clave]);
    }

    public static function limpiar(string $clave): void
    {
        DB::consulta('DELETE FROM intentos_acceso WHERE clave = ?', [$clave]);
    }
}
