<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\App;
use App\Core\Auth;
use App\Core\DB;

/** Bitácora de acciones sensibles (HU-05). Solo inserción: la base rechaza editar o borrar. */
final class Bitacora
{
    public static function registrar(
        string $accion,
        ?string $entidad = null,
        int|string|null $entidadId = null,
        ?array $detalle = null,
        ?int $usuarioId = null,
        ?string $entidadNombre = null,
    ): void {
        DB::insertar(
            'INSERT INTO bitacora (usuario_id, accion, entidad, entidad_id, entidad_nombre, detalle, ip) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $usuarioId ?? Auth::id(),
                $accion,
                $entidad,
                $entidadId === null ? null : (string) $entidadId,
                $entidadNombre,
                $detalle === null ? null : json_encode($detalle, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                App::peticion()?->ip ?: null,
            ],
        );
    }

    /** Últimos registros de una acción (lo usan las pruebas). */
    public static function ultimos(string $accion, int $cantidad = 10): array
    {
        return DB::filas(
            'SELECT * FROM bitacora WHERE accion = ? ORDER BY id DESC LIMIT ' . max(1, $cantidad),
            [$accion],
        );
    }

    /**
     * Registros filtrados, del más nuevo al más viejo, con el nombre de quien actuó
     * y de la entidad afectada.
     *
     * @param array{desde?: string, hasta?: string, usuario?: string, acciones?: string[], buscar?: string} $f
     * @return array{0: array, 1: int} filas de la página y total de registros que cumplen el filtro
     */
    public static function buscar(array $f, int $pagina, int $porPagina): array
    {
        $donde = [];
        $p = [];
        if (!empty($f['desde'])) {
            $donde[] = 'b.creado_en >= ?';
            $p[] = $f['desde'] . ' 00:00:00';
        }
        if (!empty($f['hasta'])) {
            $donde[] = 'b.creado_en < DATE_ADD(?, INTERVAL 1 DAY)';
            $p[] = $f['hasta'];
        }
        if (($f['usuario'] ?? '') === 'ninguno') {
            $donde[] = 'b.usuario_id IS NULL';
        } elseif (!empty($f['usuario'])) {
            $donde[] = 'b.usuario_id = ?';
            $p[] = (int) $f['usuario'];
        }
        if (!empty($f['acciones'])) {
            $donde[] = 'b.accion IN (' . implode(', ', array_fill(0, count($f['acciones']), '?')) . ')';
            array_push($p, ...$f['acciones']);
        }
        if (!empty($f['buscar'])) {
            // El detalle es JSON (intercalación binaria): se compara sin distinguir mayúsculas
            $donde[] = '(b.accion LIKE ? OR b.ip LIKE ? OR u.nombre LIKE ?
                OR CONVERT(b.detalle USING utf8mb4) COLLATE utf8mb4_unicode_ci LIKE ?)';
            $patron = '%' . addcslashes($f['buscar'], '%_\\') . '%';
            array_push($p, $patron, $patron, $patron, $patron);
        }
        $where = $donde ? 'WHERE ' . implode(' AND ', $donde) : '';

        $total = (int) DB::valor("SELECT COUNT(*) FROM bitacora b LEFT JOIN usuarios u ON u.id = b.usuario_id {$where}", $p);

        $desde = max(0, ($pagina - 1) * $porPagina);
        $filas = DB::filas(
            "SELECT b.*, u.nombre AS usuario_nombre,
                    eu.nombre AS entidad_usuario, ep.codigo AS entidad_codigo, ep.nombre AS entidad_producto,
                    ec.nombre AS entidad_categoria, ek.fecha AS entidad_caja_fecha, et.nombre AS entidad_caja_turno,
                    etr.nombre AS entidad_turno
             FROM bitacora b
             LEFT JOIN usuarios u ON u.id = b.usuario_id
             LEFT JOIN usuarios eu ON b.entidad = 'usuarios' AND eu.id = b.entidad_id
             LEFT JOIN productos ep ON b.entidad = 'productos' AND ep.id = b.entidad_id
             LEFT JOIN categorias ec ON b.entidad = 'categorias' AND ec.id = b.entidad_id
             LEFT JOIN cajas ek ON b.entidad = 'cajas' AND ek.id = b.entidad_id
             LEFT JOIN turnos et ON et.id = ek.turno_id
             LEFT JOIN turnos etr ON b.entidad = 'turnos' AND etr.id = b.entidad_id
             {$where}
             ORDER BY b.creado_en DESC, b.id DESC
             LIMIT {$porPagina} OFFSET {$desde}",
            $p,
        );
        return [$filas, $total];
    }

    /** Usuarios que aparecen en la bitácora, para el filtro. */
    public static function usuarios(): array
    {
        return DB::filas(
            'SELECT DISTINCT u.id, u.nombre FROM bitacora b JOIN usuarios u ON u.id = b.usuario_id ORDER BY u.nombre'
        );
    }
}
