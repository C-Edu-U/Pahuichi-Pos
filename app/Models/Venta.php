<?php

declare(strict_types=1);
namespace App\Models;

use App\Core\DB;

/** Soporte de HU-57: venta en efectivo y líneas con el precio al vender. */
final class Venta
{
    public static function porUuid(string $uuid): ?array
    {
        return DB::fila('SELECT * FROM ventas WHERE uuid = ?', [$uuid]);
    }

    public static function cajaParaCobrar(int $id): ?array
    {
        return DB::fila('SELECT * FROM cajas WHERE id = ? FOR UPDATE', [$id]);
    }

    public static function productosParaCobrar(array $ids): array
    {
        $marcas = implode(',', array_fill(0, count($ids), '?'));
        return DB::filas('SELECT p.*, c.activo AS categoria_activa FROM productos p JOIN categorias c ON c.id = p.categoria_id WHERE p.id IN (' . $marcas . ') ORDER BY p.id LOCK IN SHARE MODE', $ids);
    }

    public static function crear(array $d): int
    {
        return DB::insertar('INSERT INTO ventas (uuid, solicitud_hash, caja_id, usuario_id, tipo_pedido, mesa, subtotal, total, creado_en) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', array_values($d));
    }

    public static function detalle(int $id, array $l): void
    {
        DB::insertar('INSERT INTO detalle_venta (venta_id, producto_id, descripcion, cantidad, precio_unitario, subtotal, nota) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$id, $l['producto_id'], $l['nombre'], $l['cantidad'], $l['precio'], $l['subtotal'], $l['nota'] ?: null]);
    }

    public static function pago(int $id, string $total, string $recibido, string $cambio): void
    {
        DB::insertar("INSERT INTO pagos (venta_id, metodo, monto, recibido, cambio) VALUES (?, 'efectivo', ?, ?, ?)", [$id, $total, $recibido, $cambio]);
    }
}
