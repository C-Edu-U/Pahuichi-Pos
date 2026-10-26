<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\ErrorHttp;
use App\Core\Peticion;
use App\Core\Reloj;
use App\Core\Respuesta;
use App\Models\Categoria;
use App\Models\ConfiguracionTickets;
use App\Models\Cuenta;
use App\Models\Factura;
use App\Models\Producto;
use App\Models\Promocion;
use App\Services\AutorizacionInvalida;
use App\Services\CajaService;
use App\Services\CuentaService;
use App\Services\PromocionService;
use App\Services\TicketsPdf;
use DateTimeImmutable;
use DomainException;
use JsonException;

/**
 * Cuentas por mesa (HU-35): pantalla del mesero, pedidos (rondas), comandas, cierre y cobro.
 * Caja usa las mismas rutas desde el punto de venta.
 */
final class CuentaController extends Controller
{
    /** Pantalla del mesero, pensada para el celular. */
    public function mesero(Peticion $peticion): Respuesta
    {
        $productos = Producto::paraPos();
        $conProductos = array_flip(array_column($productos, 'categoria_id'));
        return $this->vista('mesero/index', [
            'titulo' => 'Pedidos',
            'modulo' => 'mesero',
            'h1' => 'Pedidos',
            'css' => ['pos.css', 'mesero.css'],
            'js' => ['carrito.js', 'motor-promociones.js', 'cuentas.js', 'mesero.js'],
            'claseBody' => 'panel--mesero',
            'caja' => CajaService::abierta(),
            'productos' => $productos,
            'categorias' => array_values(array_filter(Categoria::activas(), static fn (array $c): bool => isset($conProductos[$c['id']]))),
            'promociones' => Promocion::activas(),
            'contextoPromos' => PromocionService::contexto(Reloj::ahora()),
            'ahora' => Reloj::ahora(),
            'hayFavoritos' => in_array(1, array_map('intval', array_column($productos, 'favorito')), true),
        ]);
    }

    /** Cuentas abiertas y las cerradas de hoy, con sus pedidos. Para caja y para los meseros. */
    public function lista(Peticion $peticion): Respuesta
    {
        $yo = Auth::usuario();
        $cuentas = array_map(static function (array $c) use ($yo): array {
            $rondas = array_map(static fn (array $r): array => [
                'id' => (int) $r['id'],
                'numero' => (int) $r['numero'],
                'hora' => substr((string) $r['creado_en'], 11, 5),
                'usuario' => $r['usuario'],
                'estado' => $r['estado'],
                'subtotal' => $r['subtotal'],
                'promociones' => $r['promociones'],
                'motivo' => $r['motivo_cancelacion'],
                'comanda_vista' => $r['comanda_vista_en'] !== null,
                'comanda' => '/cuentas/rondas/' . (int) $r['id'] . '/comanda',
                'cancelable' => $c['estado'] === 'abierta' && $r['estado'] === 'enviada' && ($yo['rol'] !== 'mesero' || (int) $r['usuario_id'] === (int) $yo['id']),
                'lineas' => array_map(static fn (array $l): array => [
                    'nombre' => $l['nombre'], 'cantidad' => $l['cantidad'], 'subtotal' => $l['subtotal'], 'descuento' => $l['descuento'],
                    'nota' => $l['nota'], 'regalo' => !empty($l['regalo']),
                    'promociones' => array_column($l['promociones'], 'etiqueta'),
                ], $r['lineas']),
            ], $c['rondas']);
            $activas = array_filter($rondas, static fn (array $r): bool => $r['estado'] === 'enviada');
            $centavos = array_sum(array_map(static fn (array $r): int => (int) round((float) $r['subtotal'] * 100), $activas));
            return [
                'id' => (int) $c['id'],
                'mesa' => $c['mesa'],
                'estado' => $c['estado'],
                'abierta_por' => $c['abierta_por_nombre'],
                'abierta' => substr((string) $c['abierta_en'], 11, 5),
                'cerrada' => $c['cerrada_en'] !== null ? substr((string) $c['cerrada_en'], 11, 5) : null,
                'cerrada_por' => $c['cerrada_por_nombre'],
                'total' => $c['estado'] === 'cerrada' ? $c['total'] : number_format($centavos / 100, 2, '.', ''),
                'factura' => $c['factura_numero'] !== null ? (int) $c['factura_numero'] : null,
                'tickets' => $c['factura_id'] !== null ? '/cuentas/' . (int) $c['id'] . '/factura' : null,
                'nuevas' => count(array_filter($activas, static fn (array $r): bool => !$r['comanda_vista'])),
                'rondas' => $rondas,
            ];
        }, Cuenta::delDia(Reloj::ahora()->format('Y-m-d')));

        return Respuesta::json(['caja' => CajaService::abierta() !== null, 'cuentas' => $cuentas]);
    }

    public function enviarRonda(Peticion $peticion): Respuesta
    {
        return $this->json(static function () use ($peticion): array {
            $d = $peticion->datos;
            if (!is_string($d['lineas'] ?? null)) throw new DomainException('Revisa los productos del pedido.');
            $d['lineas'] = json_decode($d['lineas'], true, 32, JSON_THROW_ON_ERROR);
            return CuentaService::enviarRonda($d);
        });
    }

    public function cancelarRonda(Peticion $peticion): Respuesta
    {
        return $this->json(static function () use ($peticion): array {
            CuentaService::cancelarRonda((int) $peticion->parametro('id'), (string) $peticion->entrada('motivo'));
            return ['ok' => true];
        });
    }

    public function cerrar(Peticion $peticion): Respuesta
    {
        return $this->json(static function () use ($peticion): array {
            $f = CuentaService::cerrar((int) $peticion->parametro('id'), $peticion->datos);
            $s = $f['snapshot'] ?? json_decode($f['snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
            return ['numero' => (int) $f['numero'], 'pedido' => $s['pedido'], 'total' => $s['total'], 'cambio' => $s['cambio'],
                'tickets' => '/cuentas/' . (int) $peticion->parametro('id') . '/factura'];
        });
    }

    /** Comanda de una ronda para cocina, con el perfil de cocina (HU-12). Caja la imprime. */
    public function comanda(Peticion $peticion): Respuesta
    {
        $ronda = Cuenta::ronda((int) $peticion->parametro('id')) ?? throw ErrorHttp::noEncontrado('Ese pedido no existe.');
        $cuenta = Cuenta::buscar((int) $ronda['cuenta_id']);
        if (Auth::usuario()['rol'] !== 'mesero') {
            Cuenta::marcarComandaVista((int) $ronda['id'], Reloj::sql());
        }
        $momento = new DateTimeImmutable((string) $ronda['creado_en']);
        $snapshot = [
            'pedido' => str_pad((string) $cuenta['id'], 3, '0', STR_PAD_LEFT) . '-' . (int) $ronda['numero'],
            'tipo' => 'mesa', 'mesa' => $cuenta['mesa'], 'fecha_larga' => $momento->format('d/m/Y H:i'),
            'lineas' => Cuenta::lineasDeRonda((int) $ronda['id']),
        ];
        $perfil = ConfiguracionTickets::todas()['cocina'];
        $bytes = TicketsPdf::generar($snapshot, ['perfil' => $perfil, 'tipos' => ['comanda']]);
        $nombre = 'Comanda-mesa-' . $cuenta['mesa'] . '-pedido-' . (int) $ronda['numero'] . '.pdf';
        return $this->pdf($bytes, $nombre, $peticion->consulta('descargar') === '1');
    }

    /** Factura y copia de caja de una cuenta cerrada (sin comandas: salieron con cada ronda). */
    public function factura(Peticion $peticion): Respuesta
    {
        $cuenta = Cuenta::buscar((int) $peticion->parametro('id')) ?? throw ErrorHttp::noEncontrado('Esa cuenta no existe.');
        if ($cuenta['venta_id'] === null) throw ErrorHttp::noEncontrado('Esa cuenta todavía no se cobró.');
        $f = Factura::buscar((int) Factura::deVenta((int) $cuenta['venta_id'])['id']);
        try {
            $salida = TicketsPdf::preparar('caja');
        } catch (DomainException $e) {
            throw new ErrorHttp(422, $e->getMessage());
        }
        $salida['tipos'] = array_values(array_diff($salida['tipos'], ['comanda']));
        if ($salida['tipos'] === []) throw new ErrorHttp(422, 'El perfil de caja solo genera comandas; habilita la factura o la copia de caja.');
        return $this->pdf(TicketsPdf::generar($f['snapshot'], $salida), 'Cuenta-mesa-' . $cuenta['mesa'] . '-factura-' . (int) $f['numero'] . '.pdf',
            $peticion->consulta('descargar') === '1');
    }

    private function pdf(string $bytes, string $nombre, bool $descargar): Respuesta
    {
        return new Respuesta($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($descargar ? 'attachment' : 'inline') . '; filename="' . $nombre . '"',
            'Content-Length' => (string) strlen($bytes),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Respuesta JSON de una acción: los errores de negocio van como 422 con su mensaje. */
    private function json(callable $accion): Respuesta
    {
        try {
            return Respuesta::json($accion());
        } catch (AutorizacionInvalida $e) {
            return Respuesta::json(['error' => $e->getMessage(), 'descuento' => 'invalido'], 422);
        } catch (DomainException | JsonException $e) {
            return Respuesta::json(['error' => $e instanceof JsonException ? 'Revisa los productos del pedido.' : $e->getMessage()], 422);
        }
    }
}
