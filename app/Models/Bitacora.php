<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\App;
use App\Core\Auth;
use App\Core\DB;

/** Bitácora de acciones sensibles. Solo inserción: la base rechaza editar o borrar. */
final class Bitacora
{
    public static function registrar(
        string $accion,
        ?string $entidad = null,
        int|string|null $entidadId = null,
        ?array $detalle = null,
        ?int $usuarioId = null,
    ): void {
        DB::insertar(
            'INSERT INTO bitacora (usuario_id, accion, entidad, entidad_id, detalle, ip) VALUES (?, ?, ?, ?, ?, ?)',
            [
                $usuarioId ?? Auth::id(),
                $accion,
                $entidad,
                $entidadId === null ? null : (string) $entidadId,
                $detalle === null ? null : json_encode($detalle, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                App::peticion()?->ip ?: null,
            ],
        );
    }

    /** Últimos registros de una acción (para pruebas y, en el Sprint 2, la pantalla de bitácora). */
    public static function ultimos(string $accion, int $cantidad = 10): array
    {
        return DB::filas(
            'SELECT * FROM bitacora WHERE accion = ? ORDER BY id DESC LIMIT ' . max(1, $cantidad),
            [$accion],
        );
    }
}
