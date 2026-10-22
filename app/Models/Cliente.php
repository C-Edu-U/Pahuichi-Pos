<?php

declare(strict_types=1);
namespace App\Models;

use App\Core\DB;

final class Cliente
{
    public static function porDocumento(string $documento, bool $bloquear = false): ?array
    {
        return DB::fila('SELECT id, tipo, documento AS doc, nombre FROM clientes WHERE documento = ?' . ($bloquear ? ' FOR UPDATE' : ''), [$documento]);
    }

    public static function sugerencias(string $prefijo): array
    {
        return DB::filas('SELECT id, tipo, documento AS doc, nombre FROM clientes WHERE documento LIKE ? ORDER BY documento LIMIT 5', [$prefijo . '%']);
    }

    public static function listar(string $q): array
    {
        $patron = '%' . addcslashes($q, '%_\\') . '%';
        return DB::filas('SELECT id, tipo, documento AS doc, nombre FROM clientes WHERE documento LIKE ? OR nombre LIKE ? ORDER BY nombre, id LIMIT 100', [$patron, $patron]);
    }

    public static function crear(array $d): int
    {
        return DB::insertar('INSERT INTO clientes (tipo, documento, nombre) VALUES (?, ?, ?)', [$d['tipo'], $d['doc'], $d['nombre']]);
    }
}
