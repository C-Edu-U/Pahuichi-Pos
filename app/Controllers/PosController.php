<?php

declare(strict_types=1);
namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Peticion;
use App\Core\Respuesta;
use App\Models\Categoria;
use App\Models\Producto;
use App\Services\CajaService;
use App\Services\VentaService;
use DomainException;
use JsonException;

/** Soporte mínimo de POS para verificar la leyenda de HU-10 en una venta guardada. */
final class PosController extends Controller
{
    public function index(Peticion $peticion): Respuesta
    {
        return $this->vista('pos/index', [
            'titulo' => 'Punto de venta', 'modulo' => 'pos', 'h1' => 'Punto de venta',
            'css' => ['pos.css', 'pos-hu10.css'], 'js' => ['pos.js'], 'rielPlegado' => true,
            'caja' => CajaService::abierta(), 'proximoPedido' => 1 + (int) DB::valor('SELECT COALESCE(MAX(id), 0) FROM ventas'), 'categorias' => Categoria::activas(),
            'productos' => array_map(static fn (array $p): array => $p + ['clase_categoria' => Categoria::clase($p['categoria_color'])], array_values(array_filter(Producto::listar(), static fn (array $p): bool => (bool) $p['activo'] && (bool) $p['categoria_activa']))),
        ]);
    }

    public function cobrar(Peticion $peticion): Respuesta
    {
        $d = $peticion->datos;
        try {
            if (!is_string($d['lineas'] ?? null)) throw new DomainException('Revisa los productos del pedido.');
            $d['lineas'] = json_decode($d['lineas'], true, 32, JSON_THROW_ON_ERROR);
            $f = VentaService::cobrar($d);
            $s = isset($f['snapshot']) ? $f['snapshot'] : json_decode($f['snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
            return Respuesta::json(['id' => (int) $f['id'], 'numero' => (int) $f['numero'], 'pedido' => $s['pedido'], 'cambio' => $s['cambio'], 'tickets' => '/facturas/' . $f['id']]);
        } catch (DomainException | JsonException $e) {
            return Respuesta::json(['error' => $e instanceof JsonException ? 'Revisa los productos del pedido.' : $e->getMessage()], 422);
        }
    }
}
