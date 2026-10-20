<?php

declare(strict_types=1);
namespace App\Models;

use App\Core\DB;
use DomainException;

/** HU-10: consultas al catálogo de leyendas. */
final class Leyenda
{
    public static function todas(bool $bloquear = false): array
    {
        return DB::filas('SELECT * FROM leyendas ORDER BY id' . ($bloquear ? ' FOR UPDATE' : ''));
    }

    public static function crear(string $texto): int
    {
        return DB::insertar('INSERT INTO leyendas (texto) VALUES (?)', [$texto]);
    }

    public static function cambiarEstado(int $id, bool $activo): void
    {
        DB::consulta('UPDATE leyendas SET activo = ? WHERE id = ?', [(int) $activo, $id]);
    }

    /** Se llama al emitir dentro de la transacción; la factura guarda id y texto. */
    public static function alAzar(): array
    {
        $activas = DB::filas('SELECT id, texto FROM leyendas WHERE activo = 1 ORDER BY id LOCK IN SHARE MODE');
        if (!$activas) {
            throw new DomainException('No hay leyendas activas. Revisa el catálogo antes de vender.');
        }
        return $activas[random_int(0, count($activas) - 1)];
    }
}
