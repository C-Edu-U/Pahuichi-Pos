<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

/** Cajas por turno (HU-20). Cierre y arqueo llegan en el Sprint 2 (HU-23, HU-24). */
final class Caja
{
    private const SELECT = 'SELECT c.*, t.nombre AS turno, t.hora_inicio, t.hora_fin, u.nombre AS cajero, a.nombre AS abierta_por_nombre
        FROM cajas c
        JOIN turnos t ON t.id = c.turno_id
        JOIN usuarios u ON u.id = c.cajero_id
        JOIN usuarios a ON a.id = c.abierta_por';

    public static function abierta(): ?array
    {
        return DB::fila(self::SELECT . " WHERE c.estado = 'abierta'");
    }

    public static function delTurno(string $fecha, int $turnoId): ?array
    {
        return DB::fila(self::SELECT . ' WHERE c.fecha = ? AND c.turno_id = ?', [$fecha, $turnoId]);
    }

    public static function ultimas(int $cantidad = 10): array
    {
        return DB::filas(self::SELECT . ' WHERE c.archivada = 0 ORDER BY c.abierta_en DESC LIMIT ' . max(1, $cantidad));
    }

    /** Última caja cerrada, para mostrar su arqueo. */
    public static function ultimaCerrada(): ?array
    {
        return DB::fila(self::SELECT . " WHERE c.estado = 'cerrada' ORDER BY c.cerrada_en DESC, c.id DESC LIMIT 1");
    }

    public static function buscar(int $id): ?array
    {
        return DB::fila(self::SELECT . ' WHERE c.id = ?', [$id]);
    }

    public static function crear(int $turnoId, string $fecha, int $cajeroId, int $abiertaPor, float $fondo, string $abiertaEn): int
    {
        return DB::insertar(
            'INSERT INTO cajas (turno_id, fecha, cajero_id, abierta_por, fondo_inicial, abierta_en) VALUES (?, ?, ?, ?, ?, ?)',
            [$turnoId, $fecha, $cajeroId, $abiertaPor, $fondo, $abiertaEn],
        );
    }

    public static function cerrar(int $id, ?string $cerradaEn = null): void
    {
        DB::consulta('UPDATE cajas SET estado = ?, cerrada_en = ? WHERE id = ?', ['cerrada', $cerradaEn, $id]);
    }

    /** Oculta del historial las cajas cerradas; sus datos se conservan (RNF-20). */
    public static function archivarCerradas(): int
    {
        return (int) DB::consulta("UPDATE cajas SET archivada = 1 WHERE estado = 'cerrada' AND archivada = 0")->rowCount();
    }
}
