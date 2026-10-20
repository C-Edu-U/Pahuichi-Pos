<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use App\Core\Reloj;

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

    /** Cambia el nombre del turno. La clave interna no cambia. */
    public static function cambiarNombre(int $id, string $nombre): void
    {
        DB::consulta('UPDATE turnos SET nombre = ? WHERE id = ?', [$nombre, $id]);
    }

    /** «11:00 a 16:00». La medianoche se muestra como 23:59, como la marca el administrador. */
    public static function horario(array $t): string
    {
        return self::hora($t['hora_inicio']) . ' a ' . self::hora($t['hora_fin']);
    }

    /** Hora de pantalla: la base guarda 24:00:00 para la medianoche, pero se ve 23:59. */
    public static function hora(string $hora): string
    {
        return $hora === '24:00:00' ? '23:59' : substr($hora, 0, 5);
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

    /** El turno ya tiene cajas hoy: no se puede eliminar (la llave foránea lo impide). */
    public static function tieneCajasHoy(int $id): bool
    {
        $hoy = Reloj::ahora()->format('Y-m-d');
        return (int) DB::valor('SELECT COUNT(*) FROM cajas WHERE turno_id = ? AND fecha = ?', [$id, $hoy]) > 0;
    }

    /** El turno tiene cajas en cualquier fecha: la llave foránea impide eliminarlo. */
    public static function tieneCajas(int $id): bool
    {
        return (int) DB::valor('SELECT COUNT(*) FROM cajas WHERE turno_id = ?', [$id]) > 0;
    }
}
