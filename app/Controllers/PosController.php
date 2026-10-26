<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Peticion;
use App\Core\Reloj;
use App\Core\Respuesta;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Promocion;
use App\Services\AutorizacionInvalida;
use App\Services\CajaService;
use App\Services\PromocionService;
use App\Services\VentaService;
use DomainException;
use JsonException;

/**
 * Punto de venta. El middleware «caja» ya garantizó que hay una caja abierta (HU-21).
 * HU-28: cuadrícula de productos con favoritos primero, categorías y búsqueda.
 * HU-29: carrito en el navegador (carrito.js); el precio al cobrar lo valida VentaService (soporte de HU-57).
 * HU-46: las promociones activas viajan a la pantalla para que motor-promociones.js las aplique al agregar.
 * HU-49: también la hora del servidor y los usos de hoy, para las restricciones de horario y tope.
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
            'css' => ['pos.css', 'qr-dia.css'],
            'js' => ['carrito.js', 'motor-promociones.js', 'cliente-pos.js', 'cola-ventas.js', 'cuentas.js', 'pos.js', 'qr-dia.js', 'qr-cobro.js'],
            'claseBody' => 'panel--pos',
            'rielPlegado' => true,
            'caja' => CajaService::abierta(),
            'productos' => $productos,
            'categorias' => $categorias,
            'promociones' => Promocion::activas(),
            'contextoPromos' => PromocionService::contexto(Reloj::ahora()),
            'ahora' => Reloj::ahora(),
            'hayFavoritos' => in_array(1, array_map('intval', array_column($productos, 'favorito')), true),
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
            return Respuesta::json(['id' => (int) $f['id'], 'numero' => (int) $f['numero'], 'pedido' => $s['pedido'], 'cambio' => $s['cambio'], 'pago_qr' => $s['pago_qr'] ?? null, 'pago_efectivo' => $s['pago_efectivo'] ?? null, 'tickets' => '/facturas/' . $f['id']]);
        } catch (\App\Services\ComprobanteQrDuplicado $e) {
            return Respuesta::json(['error' => $e->getMessage(), 'comprobante_duplicado' => $e->original], 422);
        } catch (AutorizacionInvalida $e) {
            // El POS quita el descuento para pedir el PIN otra vez
            return Respuesta::json(['error' => $e->getMessage(), 'descuento' => 'invalido'], 422);
        } catch (DomainException | JsonException $e) {
            return Respuesta::json(['error' => $e instanceof JsonException ? 'Revisa los productos del pedido.' : $e->getMessage()], 422);
        }
    }
}
