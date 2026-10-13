<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

/** Turnos de caja (HU-11): medio día y noche, con horario configurable. */
final class Turno
{
    public static function todos(): array
    {
        return DB::filas('SELECT * FROM turnos ORDER BY orden');
    }

    public static function activos(): array
    {
        return DB::filas('SELECT * FROM turnos WHERE activo = 1 ORDER BY orden');
    }

    public static function buscar(int $id): ?array
    {
        return DB::fila('SELECT * FROM turnos WHERE id = ?', [$id]);
    }

    public static function cambiarHorario(int $id, string $inicio, string $fin): void
    {
        DB::consulta('UPDATE turnos SET hora_inicio = ?, hora_fin = ? WHERE id = ?', [$inicio, $fin, $id]);
    }

    /** «11:00 a 16:00»; el fin 24:00:00 se muestra como 24:00. */
    public static function horario(array $t): string
    {
        return self::hora($t['hora_inicio']) . ' a ' . self::hora($t['hora_fin']);
    }

    public static function hora(string $hora): string
    {
        return substr($hora, 0, 5);
    }

    /** Valor para <input type="time">, que no admite 24:00: se muestra 23:59. */
    public static function horaControl(string $hora): string
    {
        return $hora === '24:00:00' ? '23:59' : substr($hora, 0, 5);
    }
}
