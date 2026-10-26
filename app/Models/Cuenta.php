<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

/** Cuentas por mesa y sus rondas (HU-35). Las reglas están en CuentaService. */
final class Cuenta
{
    public static function buscar(int $id, bool $bloquear = false): ?array
    {
        return DB::fila('SELECT * FROM cuentas WHERE id = ?' . ($bloquear ? ' FOR UPDATE' : ''), [$id]);
    }

    /** Mesas con la cuenta abierta (HU-35): mientras haya, la caja no se cierra. */
    public static function mesasAbiertas(): array
    {
        return array_column(DB::filas("SELECT mesa FROM cuentas WHERE estado = 'abierta' ORDER BY CAST(mesa AS UNSIGNED)"), 'mesa');
    }

    public static function abiertaDeMesa(string $mesa, bool $bloquear = false): ?array
    {
        return DB::fila("SELECT * FROM cuentas WHERE mesa_abierta = ?" . ($bloquear ? ' FOR UPDATE' : ''), [$mesa]);
    }

    public static function abrir(string $mesa, int $usuarioId, string $en): int
    {
        return DB::insertar('INSERT INTO cuentas (mesa, abierta_por, abierta_en) VALUES (?, ?, ?)', [$mesa, $usuarioId, $en]);
    }

    public static function cerrar(int $id, int $ventaId, int $usuarioId, string $en): void
    {
        DB::consulta("UPDATE cuentas SET estado = 'cerrada', venta_id = ?, cerrada_por = ?, cerrada_en = ? WHERE id = ?", [$ventaId, $usuarioId, $en, $id]);
    }

    public static function cancelar(int $id, int $usuarioId, string $en): void
    {
        DB::consulta("UPDATE cuentas SET estado = 'cancelada', cerrada_por = ?, cerrada_en = ? WHERE id = ?", [$usuarioId, $en, $id]);
    }

    // ------------------------------------------------------------------ rondas

    public static function ronda(int $id, bool $bloquear = false): ?array
    {
        return DB::fila('SELECT * FROM cuenta_rondas WHERE id = ?' . ($bloquear ? ' FOR UPDATE' : ''), [$id]);
    }

    public static function rondaPorUuid(string $uuid): ?array
    {
        return DB::fila('SELECT * FROM cuenta_rondas WHERE uuid = ?', [$uuid]);
    }

    public static function siguienteRonda(int $cuentaId): int
    {
        return (int) DB::valor('SELECT COALESCE(MAX(numero), 0) + 1 FROM cuenta_rondas WHERE cuenta_id = ?', [$cuentaId]);
    }

    public static function crearRonda(array $r): int
    {
        return DB::insertar(
            'INSERT INTO cuenta_rondas (cuenta_id, numero, uuid, solicitud_hash, usuario_id, bruto, promociones, subtotal, pedida_en, creado_en)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$r['cuenta_id'], $r['numero'], $r['uuid'], $r['hash'], $r['usuario_id'], $r['bruto'], $r['promociones'], $r['subtotal'], $r['pedida_en'], $r['creado_en']],
        );
    }

    /** Guarda la línea tal como se cobrará al cerrar: precio, descuento y promociones de su hora. */
    public static function linea(int $rondaId, array $l): void
    {
        DB::insertar('INSERT INTO ronda_lineas (ronda_id, producto_id, cantidad, subtotal, datos) VALUES (?, ?, ?, ?, ?)',
            [$rondaId, $l['producto_id'], $l['cantidad'], $l['subtotal'], json_encode($l, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
    }

    public static function cancelarRonda(int $id, int $usuarioId, string $motivo, string $en): void
    {
        DB::consulta("UPDATE cuenta_rondas SET estado = 'cancelada', cancelada_por = ?, motivo_cancelacion = ?, cancelada_en = ? WHERE id = ?",
            [$usuarioId, $motivo, $en, $id]);
    }

    public static function rondasActivas(int $cuentaId): int
    {
        return (int) DB::valor("SELECT COUNT(*) FROM cuenta_rondas WHERE cuenta_id = ? AND estado = 'enviada'", [$cuentaId]);
    }

    public static function marcarComandaVista(int $rondaId, string $en): void
    {
        DB::consulta('UPDATE cuenta_rondas SET comanda_vista_en = ? WHERE id = ? AND comanda_vista_en IS NULL', [$en, $rondaId]);
    }

    /** Líneas de las rondas enviadas (no canceladas), en el orden en que se pidieron. */
    public static function lineasActivas(int $cuentaId): array
    {
        $filas = DB::filas("SELECT l.datos FROM ronda_lineas l JOIN cuenta_rondas r ON r.id = l.ronda_id
            WHERE r.cuenta_id = ? AND r.estado = 'enviada' ORDER BY r.numero, l.id", [$cuentaId]);
        return array_map(static fn (array $f): array => json_decode((string) $f['datos'], true, 32, JSON_THROW_ON_ERROR), $filas);
    }

    public static function lineasDeRonda(int $rondaId): array
    {
        $filas = DB::filas('SELECT datos FROM ronda_lineas WHERE ronda_id = ? ORDER BY id', [$rondaId]);
        return array_map(static fn (array $f): array => json_decode((string) $f['datos'], true, 32, JSON_THROW_ON_ERROR), $filas);
    }

    // ------------------------------------------------------------------ listas

    /**
     * Cuentas abiertas con sus rondas y líneas (para caja y para los meseros), y las
     * cerradas o canceladas de hoy.
     */
    public static function delDia(string $hoy): array
    {
        $cuentas = DB::filas(
            "SELECT c.*, ua.nombre AS abierta_por_nombre, uc.nombre AS cerrada_por_nombre, f.id AS factura_id, f.numero AS factura_numero, v.total
             FROM cuentas c JOIN usuarios ua ON ua.id = c.abierta_por LEFT JOIN usuarios uc ON uc.id = c.cerrada_por
             LEFT JOIN ventas v ON v.id = c.venta_id LEFT JOIN facturas f ON f.venta_id = c.venta_id
             WHERE c.estado = 'abierta' OR c.cerrada_en >= ?
             ORDER BY c.estado = 'abierta' DESC, CAST(c.mesa AS UNSIGNED), c.cerrada_en DESC",
            [$hoy . ' 00:00:00'],
        );
        if ($cuentas === []) {
            return [];
        }
        $ids = array_map(static fn (array $c): int => (int) $c['id'], $cuentas);
        $marcas = implode(',', array_fill(0, count($ids), '?'));
        $rondas = DB::filas("SELECT r.*, u.nombre AS usuario FROM cuenta_rondas r JOIN usuarios u ON u.id = r.usuario_id
            WHERE r.cuenta_id IN ({$marcas}) ORDER BY r.numero", $ids);
        $lineas = [];
        if ($rondas !== []) {
            $rids = array_map(static fn (array $r): int => (int) $r['id'], $rondas);
            $m = implode(',', array_fill(0, count($rids), '?'));
            foreach (DB::filas("SELECT ronda_id, datos FROM ronda_lineas WHERE ronda_id IN ({$m}) ORDER BY id", $rids) as $l) {
                $lineas[(int) $l['ronda_id']][] = json_decode((string) $l['datos'], true, 32, JSON_THROW_ON_ERROR);
            }
        }
        $porCuenta = [];
        foreach ($rondas as $r) {
            $r['lineas'] = $lineas[(int) $r['id']] ?? [];
            $porCuenta[(int) $r['cuenta_id']][] = $r;
        }
        foreach ($cuentas as $i => $c) {
            $cuentas[$i]['rondas'] = $porCuenta[(int) $c['id']] ?? [];
        }
        return $cuentas;
    }
}
