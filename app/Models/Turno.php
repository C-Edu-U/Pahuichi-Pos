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

    public static function tieneCajasHoy(int $id): bool
    {
        $hoy = Reloj::ahora()->format('Y-m-d');
        return (int) DB::valor('SELECT COUNT(*) FROM cajas WHERE turno_id = ? AND fecha = ?', [$id, $hoy]) > 0;
    }

    /** El turno está en curso ahora (la base no admite turnos que crucen la medianoche). */
    public static function estaEnCurso(int $id): bool
    {
        $t = self::buscar($id);
        if ($t === null) {
            return false;
        }
        $hora = Reloj::ahora()->format('H:i:s');
        $inicio = $t['hora_inicio'];
        $fin = $t['hora_fin'] === '24:00:00' ? '23:59:59' : $t['hora_fin'];
        return $hora >= $inicio && $hora < $fin;
    }
}
