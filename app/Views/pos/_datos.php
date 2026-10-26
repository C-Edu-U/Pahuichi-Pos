<?php
/**
 * Datos del catálogo para el JS del punto de venta y del mesero (HU-28, HU-46, HU-49, HU-35).
 * Necesita $productos, $promociones, $contextoPromos y $hayFavoritos. Deja $inicial, $datos,
 * $promos (valores en centésimos) y $conPromo (promociones de cada producto y si valen ahora).
 */
use App\Services\PromocionService;

$inicial = $hayFavoritos ? 'favoritos' : 'todo';
$datos = array_map(static fn (array $p): array => [
    'id' => (int) $p['id'],
    'codigo' => $p['codigo'],
    'nombre' => $p['nombre'],
    'corto' => $p['nombre_corto'],
    'precio' => (float) $p['precio'],
    'foto' => $p['foto'],
    'agotado' => (bool) (int) $p['agotado'],
    // HU-31: la nota para cocina solo se ofrece en lo que sale en la comanda
    'cocina' => (bool) (int) $p['va_a_cocina'],
    'categoria' => (int) $p['categoria_id'],
], $productos);
// HU-46: valor en centésimos (12,50 → 1250), igual que lo calcula PromocionService.
// HU-49: restricciones y usos de hoy; montos en centavos, horas como «HH:MM:SS».
$entero = static fn (mixed $x): ?int => $x === null ? null : (int) $x;
$promos = array_map(static fn (array $pr): array => [
    'id' => (int) $pr['id'],
    'nombre' => $pr['nombre'],
    'tipo' => $pr['tipo'],
    'valor_tipo' => $pr['valor_tipo'],
    'valor' => $pr['valor'] === null ? null : PromocionService::centesimos((string) $pr['valor']),
    'producto_id' => $entero($pr['producto_id']),
    'categoria_id' => $entero($pr['categoria_id']),
    'cantidad_compra' => $entero($pr['cantidad_compra']),
    'regalo_producto_id' => $entero($pr['regalo_producto_id']),
    'precio_combo' => $pr['precio_combo'] === null ? null : PromocionService::centesimos((string) $pr['precio_combo']),
    'items' => array_map(static fn (array $it): array => ['producto_id' => $it['producto_id'], 'cantidad' => $it['cantidad']], $pr['items']),
    'fecha_desde' => $pr['fecha_desde'],
    'fecha_hasta' => $pr['fecha_hasta'],
    'dias' => (int) $pr['dias'],
    'hora_desde' => $pr['hora_desde'],
    'hora_hasta' => $pr['hora_hasta'],
    'cantidad_minima' => $entero($pr['cantidad_minima']),
    'monto_minimo' => $pr['monto_minimo'] === null ? null : PromocionService::centesimos((string) $pr['monto_minimo']),
    'tope_usos_dia' => $entero($pr['tope_usos_dia']),
    'acumulable' => (bool) (int) $pr['acumulable'],
    'prioridad' => (int) $pr['prioridad'],
    'usos' => (int) ($contextoPromos['usos'][(int) $pr['id']] ?? 0),
], $promociones);
// Promociones que tocan cada producto; la marca «Promo» se ve si alguna vale ahora (pos.js la actualiza)
$conPromo = [];
foreach ($productos as $p) {
    foreach ($promociones as $pr) {
        if ((int) $pr['producto_id'] === (int) $p['id'] || (int) $pr['categoria_id'] === (int) $p['categoria_id']
            || in_array((int) $p['id'], array_column($pr['items'], 'producto_id'), true)) {
            $conPromo[(int) $p['id']][(int) $pr['id']] = PromocionService::vigente($pr, $contextoPromos);
        }
    }
}
