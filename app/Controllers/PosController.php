<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Peticion;
use App\Core\Respuesta;
use App\Models\Categoria;
use App\Models\Producto;
use App\Services\CajaService;

/**
 * Punto de venta. El middleware «caja» ya garantizó que hay una caja abierta (HU-21).
 * HU-28: cuadrícula de productos con favoritos primero, categorías y búsqueda.
 */
final class PosController extends Controller
{
    public function index(Peticion $peticion): Respuesta
    {
        $productos = Producto::paraPos();

        // Solo las categorías que tienen algo para vender, en su orden
        $conProductos = array_flip(array_column($productos, 'categoria_id'));
        $categorias = array_values(array_filter(
            Categoria::activas(),
            static fn (array $c): bool => isset($conProductos[$c['id']]),
        ));

        return $this->vista('pos/index', [
            'titulo' => 'Punto de venta',
            'modulo' => 'pos',
            'h1' => 'Punto de venta',
            'css' => ['pos.css'],
            'js' => ['pos.js'],
            'claseBody' => 'panel--pos',
            'rielPlegado' => true,
            'caja' => CajaService::abierta(),
            'productos' => $productos,
            'categorias' => $categorias,
            'hayFavoritos' => in_array(1, array_map('intval', array_column($productos, 'favorito')), true),
        ]);
    }
}
