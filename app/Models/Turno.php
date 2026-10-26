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

    /** «11:00 a 16:00»; la medianoche (24:00:00 en la base) se ve 23:59 en todas las pantallas. */
    public static function horario(array $t): string
    {
        return self::hora($t['hora_inicio']) . ' a ' . self::hora($t['hora_fin']);
    }

    /** «18:00»; 24:00:00 se ve 23:59, que es lo que el administrador marca. */
    public static function hora(string $hora): string
    {
        return $hora === '24:00:00' ? '23:59' : substr($hora, 0, 5);
    }

    /** Valor para <input type="time">, que no admite 24:00. */
    public static function horaControl(string $hora): string
    {
        return self::hora($hora);
    }

    public static function crear(string $clave, string $nombre, string $hora_inicio, string $hora_fin, int $orden): int
    {
        return DB::insertar(
            'INSERT INTO turnos (clave, nombre, hora_inicio, hora_fin, orden, activo) VALUES (?, ?, ?, ?, ?, 1)',
            [$clave, $nombre, $hora_inicio, $hora_fin, $orden],
        );
    }

    public static function eliminar(int $id): void
    {
        DB::consulta('DELETE FROM turnos WHERE id = ?', [$id]);
    }

    /** Un turno con cajas (de cualquier día) no se borra: las cajas lo siguen nombrando. */
    public static function tieneCajas(int $id): bool
    {
        return (int) DB::valor('SELECT COUNT(*) FROM cajas WHERE turno_id = ?', [$id]) > 0;
    }
}
