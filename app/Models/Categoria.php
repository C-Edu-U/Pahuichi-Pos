<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Config;
use App\Core\DB;

/** Categorías del menú (HU-13): orden de los botones del POS y color de la paleta. */
final class Categoria
{
    /** Todas, con la cantidad de productos, en el orden del punto de venta. */
    public static function todas(): array
    {
        return DB::filas(
            'SELECT c.*, COUNT(p.id) AS productos
             FROM categorias c LEFT JOIN productos p ON p.categoria_id = c.id
             GROUP BY c.id ORDER BY c.orden, c.nombre'
        );
    }

    public static function activas(): array
    {
        return DB::filas('SELECT * FROM categorias WHERE activo = 1 ORDER BY orden, nombre');
    }

    public static function buscar(int $id): ?array
    {
        return DB::fila('SELECT * FROM categorias WHERE id = ?', [$id]);
    }

    /** ¿Hay otra categoría con ese nombre? (sin distinguir mayúsculas ni tildes) */
    public static function nombreUsado(string $nombre, int $excepto = 0): bool
    {
        return DB::valor('SELECT 1 FROM categorias WHERE nombre = ? AND id <> ?', [$nombre, $excepto]) !== null;
    }

    public static function crear(string $nombre, int $orden, string $color): int
    {
        return DB::insertar('INSERT INTO categorias (nombre, orden, color) VALUES (?, ?, ?)', [$nombre, $orden, $color]);
    }

    public static function actualizar(int $id, string $nombre, int $orden, string $color): void
    {
        DB::consulta('UPDATE categorias SET nombre = ?, orden = ?, color = ? WHERE id = ?', [$nombre, $orden, $color, $id]);
    }

    public static function cambiarEstado(int $id, bool $activa): void
    {
        DB::consulta('UPDATE categorias SET activo = ? WHERE id = ?', [$activa ? 1 : 0, $id]);
    }

    public static function siguienteOrden(): int
    {
        return min(99, (int) DB::valor('SELECT COALESCE(MAX(orden), 0) + 1 FROM categorias'));
    }

    /** Ícono del producto sin foto, según su categoría (cubiertos si no se reconoce). */
    public static function icono(string $nombre): string
    {
        $clave = strtolower(strtr($nombre, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u']));
        return ['refrescos' => 'vaso', 'sodas' => 'botella', 'vinos' => 'copa', 'postres' => 'helado', 'prueba' => 'qr'][$clave] ?? 'productos';
    }

    /** Clase CSS del color (cat--ambar...). */
    public static function clase(string $color): string
    {
        return 'cat--' . (Config::get('paleta')[$color][0] ?? 'gris');
    }
}
