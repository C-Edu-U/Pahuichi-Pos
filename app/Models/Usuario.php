<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use App\Core\Reloj;
use DateTimeImmutable;

final class Usuario
{
    public const ROLES = [
        'administrador' => 'Administrador',
        'cajero' => 'Cajero',
        'mesero' => 'Mesero',
        'cocina' => 'Cocina',
    ];

    /** Pantalla de entrada de cada rol. Cocina no entra al panel. */
    public const INICIO = [
        'administrador' => '/tablero',
        'cajero' => '/pos',
        'mesero' => '/mesero',
    ];

    public static function buscar(int $id): ?array
    {
        return DB::fila('SELECT * FROM usuarios WHERE id = ?', [$id]);
    }

    public static function buscarPorUsuario(string $usuario): ?array
    {
        return DB::fila('SELECT * FROM usuarios WHERE usuario = ?', [mb_strtolower($usuario)]);
    }

    /** Cajeros activos con PIN: el PIN se compara contra cada uno (bcrypt no permite buscarlo). */
    public static function cajerosConPin(): array
    {
        return DB::filas("SELECT * FROM usuarios WHERE rol = 'cajero' AND activo = 1 AND pin_hash IS NOT NULL");
    }

    public static function bloqueadoHasta(array $u): ?DateTimeImmutable
    {
        if ($u['bloqueado_hasta'] === null) {
            return null;
        }
        $hasta = new DateTimeImmutable($u['bloqueado_hasta']);
        return $hasta > Reloj::ahora() ? $hasta : null;
    }

    /** Suma un intento fallido y devuelve cuántos lleva. */
    public static function sumarFallo(int $id): int
    {
        DB::consulta('UPDATE usuarios SET intentos_fallidos = intentos_fallidos + 1 WHERE id = ?', [$id]);
        return (int) DB::valor('SELECT intentos_fallidos FROM usuarios WHERE id = ?', [$id]);
    }

    public static function bloquear(int $id, DateTimeImmutable $hasta): void
    {
        DB::consulta('UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = ? WHERE id = ?', [Reloj::sql($hasta), $id]);
    }

    public static function registrarIngreso(int $id): void
    {
        DB::consulta(
            'UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL, ultimo_ingreso = ? WHERE id = ?',
            [Reloj::sql(), $id],
        );
    }

    public static function cambiarClave(int $id, string $clave, bool $temporal = false): void
    {
        DB::consulta(
            'UPDATE usuarios SET password_hash = ?, debe_cambiar_clave = ? WHERE id = ?',
            [password_hash($clave, PASSWORD_BCRYPT), $temporal ? 1 : 0, $id],
        );
    }
}
