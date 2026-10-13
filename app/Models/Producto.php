<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

/** Productos del catálogo (HU-14). El código es único y no cambia: lo usan ventas y promociones. */
final class Producto
{
    public const UNIDADES = [
        'plato' => 'Plato',
        'porcion' => 'Porción',
        'vaso' => 'Vaso',
        'jarra' => 'Jarra',
        'botella' => 'Botella',
        'copa' => 'Copa',
        'unidad' => 'Unidad',
    ];

    /** Campos que guarda el formulario. */
    public const CAMPOS = ['nombre', 'nombre_corto', 'descripcion', 'categoria_id', 'precio', 'unidad', 'activo', 'favorito', 'va_a_cocina', 'agotado'];

    public static function listar(): array
    {
        return DB::filas(
            'SELECT p.*, c.nombre AS categoria, c.color AS categoria_color, c.activo AS categoria_activa
             FROM productos p JOIN categorias c ON c.id = p.categoria_id
             ORDER BY c.orden, p.codigo'
        );
    }

    public static function buscar(int $id): ?array
    {
        return DB::fila('SELECT * FROM productos WHERE id = ?', [$id]);
    }

    public static function codigoUsado(string $codigo): bool
    {
        return DB::valor('SELECT 1 FROM productos WHERE codigo = ?', [$codigo]) !== null;
    }

    public static function crear(string $codigo, array $d): int
    {
        return DB::insertar(
            'INSERT INTO productos (codigo, nombre, nombre_corto, descripcion, categoria_id, precio, unidad, activo, favorito, va_a_cocina, agotado)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$codigo, ...self::valores($d)],
        );
    }

    public static function actualizar(int $id, array $d): void
    {
        DB::consulta(
            'UPDATE productos SET nombre = ?, nombre_corto = ?, descripcion = ?, categoria_id = ?, precio = ?, unidad = ?,
             activo = ?, favorito = ?, va_a_cocina = ?, agotado = ? WHERE id = ?',
            [...self::valores($d), $id],
        );
    }

    /** Marca de un toque: favorito o agotado. */
    public static function marcar(int $id, string $campo, bool $valor): void
    {
        if (!in_array($campo, ['favorito', 'agotado'], true)) {
            throw new \InvalidArgumentException("Campo no marcable: {$campo}");
        }
        DB::consulta("UPDATE productos SET {$campo} = ? WHERE id = ?", [$valor ? 1 : 0, $id]);
    }

    private static function valores(array $d): array
    {
        return [
            $d['nombre'], $d['nombre_corto'], $d['descripcion'] !== '' ? $d['descripcion'] : null, $d['categoria_id'],
            $d['precio'], $d['unidad'], $d['activo'], $d['favorito'], $d['va_a_cocina'], $d['agotado'],
        ];
    }
}
