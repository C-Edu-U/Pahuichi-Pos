<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

/**
 * Promociones (HU-46): descuento por producto o por categoría, en % o en Bs por unidad.
 * HU-49: restricciones de fechas, días, horario, cantidad y monto mínimos y tope por día.
 * HU-50: acumulable o no, y prioridad entre las acumulables.
 * HU-47: combo con sus productos en promocion_items y su precio especial.
 */
final class Promocion
{
    private const SELECT = 'SELECT pr.*, p.nombre AS producto, p.precio AS producto_precio, c.nombre AS categoria, rg.nombre AS regalo
        FROM promociones pr
        LEFT JOIN productos p ON p.id = pr.producto_id
        LEFT JOIN categorias c ON c.id = pr.categoria_id
        LEFT JOIN productos rg ON rg.id = pr.regalo_producto_id';

    /** Todas para la administración: las activas primero. */
    public static function todas(): array
    {
        return self::conItems(DB::filas(self::SELECT . ' ORDER BY pr.activo DESC, pr.nombre'));
    }

    public static function buscar(int $id): ?array
    {
        $p = DB::fila(self::SELECT . ' WHERE pr.id = ?', [$id]);
        return $p === null ? null : self::conItems([$p])[0];
    }

    /**
     * Agrega a cada combo sus productos: [producto_id, cantidad, nombre, precio], por producto.
     * @param list<array> $promociones
     */
    private static function conItems(array $promociones): array
    {
        // array_values: PDO necesita las claves 0, 1, 2… y array_filter conserva las originales
        $ids = array_values(array_map(static fn (array $p): int => (int) $p['id'], array_filter($promociones, static fn (array $p): bool => $p['tipo'] === 'combo')));
        $items = [];
        if ($ids) {
            $marcas = implode(',', array_fill(0, count($ids), '?'));
            foreach (DB::filas("SELECT i.promocion_id, i.producto_id, i.cantidad, p.nombre, p.precio FROM promocion_items i
                    JOIN productos p ON p.id = i.producto_id WHERE i.promocion_id IN ({$marcas}) ORDER BY i.promocion_id, i.producto_id", $ids) as $it) {
                $items[(int) $it['promocion_id']][] = ['producto_id' => (int) $it['producto_id'], 'cantidad' => (int) $it['cantidad'],
                    'nombre' => $it['nombre'], 'precio' => $it['precio']];
            }
        }
        return array_map(static fn (array $p): array => $p + ['items' => $items[(int) $p['id']] ?? []], $promociones);
    }

    /** Reemplaza los productos de un combo. */
    public static function guardarItems(int $id, array $items): void
    {
        DB::consulta('DELETE FROM promocion_items WHERE promocion_id = ?', [$id]);
        foreach ($items as $it) {
            DB::insertar('INSERT INTO promocion_items (promocion_id, producto_id, cantidad) VALUES (?, ?, ?)', [$id, $it['producto_id'], $it['cantidad']]);
        }
    }

    /** Las que el motor evalúa al vender. */
    public static function activas(): array
    {
        return self::conItems(DB::filas(
            'SELECT id, nombre, tipo, valor_tipo, valor, producto_id, categoria_id, cantidad_compra, regalo_producto_id, precio_combo, fecha_desde, fecha_hasta, dias,
                    hora_desde, hora_hasta, cantidad_minima, monto_minimo, tope_usos_dia, acumulable, prioridad
             FROM promociones WHERE activo = 1 ORDER BY id'
        ));
    }

    /** Columnas que guarda el formulario, en orden. */
    private const CAMPOS = ['nombre', 'tipo', 'valor_tipo', 'valor', 'producto_id', 'categoria_id', 'cantidad_compra', 'regalo_producto_id', 'precio_combo', 'activo', 'fecha_desde', 'fecha_hasta',
        'dias', 'hora_desde', 'hora_hasta', 'cantidad_minima', 'monto_minimo', 'tope_usos_dia', 'acumulable', 'prioridad'];

    public static function crear(array $d): int
    {
        return DB::insertar(
            'INSERT INTO promociones (' . implode(', ', self::CAMPOS) . ') VALUES (' . implode(', ', array_fill(0, count(self::CAMPOS), '?')) . ')',
            array_map(static fn (string $c): mixed => $d[$c], self::CAMPOS),
        );
    }

    public static function actualizar(int $id, array $d): void
    {
        DB::consulta(
            'UPDATE promociones SET ' . implode(', ', array_map(static fn (string $c): string => "{$c} = ?", self::CAMPOS)) . ' WHERE id = ?',
            [...array_map(static fn (string $c): mixed => $d[$c], self::CAMPOS), $id],
        );
    }

    public static function cambiarEstado(int $id, bool $activa): void
    {
        DB::consulta('UPDATE promociones SET activo = ? WHERE id = ?', [$activa ? 1 : 0, $id]);
    }

    /**
     * HU-49: en cuántas ventas cobradas de ese día se aplicó cada promoción (una venta
     * cuenta una vez aunque la promoción toque varias líneas). Los cobros no compiten:
     * se serializan sobre la fila de la única caja abierta.
     * @return array<int, int> promocion_id => ventas
     */
    public static function usosDelDia(string $fecha): array
    {
        $filas = DB::filas(
            "SELECT vp.promocion_id, COUNT(DISTINCT vp.venta_id) AS usos FROM venta_promociones vp
             JOIN ventas v ON v.id = vp.venta_id
             WHERE v.estado = 'cobrada' AND v.creado_en >= ? AND v.creado_en < CAST(? AS DATE) + INTERVAL 1 DAY
             GROUP BY vp.promocion_id",
            [$fecha, $fecha],
        );
        return array_map('intval', array_column($filas, 'usos', 'promocion_id'));
    }

    /** En cuántas ventas cobradas descontó, en total (se muestra en la lista). */
    public static function usos(): array
    {
        $filas = DB::filas(
            "SELECT vp.promocion_id, COUNT(DISTINCT vp.venta_id) AS usos FROM venta_promociones vp
             JOIN ventas v ON v.id = vp.venta_id WHERE v.estado = 'cobrada' GROUP BY vp.promocion_id"
        );
        return array_column($filas, 'usos', 'promocion_id');
    }
}
