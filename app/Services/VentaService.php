<?php

declare(strict_types=1);
namespace App\Services;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Reloj;
use App\Models\Bitacora;
use App\Models\Factura;
use App\Models\Leyenda;
use App\Models\Negocio;
use App\Models\Producto;
use App\Models\Promocion;
use App\Models\Venta;
use DateTimeImmutable;
use DomainException;

/**
 * Soporte de HU-57: cobro en efectivo, sin promociones ni QR de pago.
 * HU-06: descuento manual con el permiso de un solo uso que entregó el PIN del
 * administrador (AutorizacionService); el permiso se gasta recién al guardar la venta.
 * HU-46: las promociones activas descuentan por línea (PromocionService). El navegador
 * envía cuánto descontaron en pantalla; si no coincide, una promoción cambió y no se cobra.
 * El regalo por compra entra como una línea más, con descuento igual a su precio (Bs 0).
 * HU-36: una venta hecha sin red llega más tarde; sus promociones se evalúan a la hora en
 * que se vendió («momento»), si esa hora es creíble: dentro de la caja abierta y de las
 * últimas 12 horas. La venta y su factura llevan la hora en que llegan al servidor.
 */
final class VentaService
{
    /** Hasta cuántas horas atrás se acepta la hora de una venta hecha sin red. */
    public const HORAS_SIN_RED = 12;

    /**
     * Hora en que se hizo la venta según el POS (la hora del servidor al cargar más lo
     * transcurrido). Si no es creíble, vale la hora de llegada.
     */
    public static function momentoDeVenta(mixed $texto, DateTimeImmutable $ahora, array $caja): DateTimeImmutable
    {
        if (!is_string($texto) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/D', $texto)) {
            return $ahora;
        }
        $momento = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s', $texto, $ahora->getTimezone());
        if ($momento === false || $momento->format('Y-m-d\TH:i:s') !== $texto) {
            return $ahora;
        }
        $abierta = new DateTimeImmutable((string) $caja['abierta_en'], $ahora->getTimezone());
        $creible = $momento <= $ahora->modify('+2 minutes')
            && $momento >= $ahora->modify('-' . self::HORAS_SIN_RED . ' hours')
            && $momento >= $abierta->modify('-1 minute');
        return $creible && $momento < $ahora ? $momento : $ahora;
    }

    public static function decimal(int $centavos): string
    {
        return intdiv($centavos, 100) . '.' . str_pad((string) ($centavos % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Monto que llega de la pantalla (texto con hasta dos decimales), en centavos. */
    public static function centavos(mixed $dato): int
    {
        if (!is_string($dato) || !preg_match('/^\d{1,8}(?:[.,]\d{1,2})?$/D', $dato)) {
            throw new DomainException('Escribe un monto válido, con hasta dos decimales.');
        }
        $p = explode('.', str_replace(',', '.', $dato));
        return (int) $p[0] * 100 + (int) str_pad($p[1] ?? '', 2, '0');
    }

    /** El identificador único del pedido o de la ronda (UUID v1 a v5). */
    public static function uuid(mixed $uuid): string
    {
        if (!is_string($uuid) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/Di', $uuid)) {
            throw new DomainException('Recarga la pantalla para identificar el pedido.');
        }
        return $uuid;
    }

    /**
     * Las líneas que manda la pantalla: producto, cantidad (1 a 999) y nota (hasta 60).
     * @return array<int, array{cantidad: int, nota: string}> producto_id => línea, ordenado
     */
    public static function cantidades(mixed $lineas): array
    {
        if (!is_array($lineas) || !$lineas || count($lineas) > 100) throw new DomainException('Agrega productos al pedido.');
        $cantidades = [];
        foreach ($lineas as $l) {
            if (!is_array($l) || !is_int($l['id'] ?? null) || $l['id'] <= 0 || !is_int($l['cantidad'] ?? null) || $l['cantidad'] < 1 || $l['cantidad'] > 999 || !is_string($l['nota'] ?? '')) {
                throw new DomainException('Revisa los productos y sus cantidades (de 1 a 999).');
            }
            if (isset($cantidades[$l['id']]) || mb_strlen($l['nota'] ?? '') > 60) throw new DomainException('Revisa las líneas repetidas o las notas del pedido.');
            $cantidades[$l['id']] = ['cantidad' => $l['cantidad'], 'nota' => trim($l['nota'] ?? '')];
        }
        ksort($cantidades);
        return $cantidades;
    }

    /**
     * Arma las líneas del pedido con los precios de hoy y las promociones que valen en
     * $momento (HU-46 a HU-50), y comprueba que coincidan con lo que vio la pantalla.
     * Va dentro de una transacción: lee los productos con bloqueo compartido.
     *
     * @return array{lineas: list<array>, bruto: int, promo: int, regalos: int, subtotal: int} en centavos
     */
    public static function armarLineas(array $cantidades, DateTimeImmutable $momento, int $esperado, int $promoEsperada, int $regalosEsperados): array
    {
        $productos = Venta::productosParaCobrar(array_keys($cantidades));
        if (count($productos) !== count($cantidades)) throw new DomainException('Un producto ya no está disponible. Revisa el pedido.');
        $lineas = [];
        $base = [];
        $total = 0;
        foreach ($productos as $p) {
            if (!$p['activo'] || !$p['categoria_activa'] || $p['agotado']) throw new DomainException($p['nombre'] . ' no está disponible. Retíralo del pedido.');
            $l = $cantidades[(int) $p['id']];
            $precio = self::centavos((string) $p['precio']);
            $sub = $precio * $l['cantidad'];
            $total += $sub;
            $base[] = ['producto_id' => (int) $p['id'], 'categoria_id' => (int) $p['categoria_id'], 'cantidad' => $l['cantidad'], 'bruto' => $sub];
            $lineas[] = ['producto_id' => (int) $p['id'], 'id' => $p['codigo'], 'nombre' => $p['nombre'], 'unidad' => Producto::UNIDADES[$p['unidad']],
                'cantidad' => $l['cantidad'], 'precio' => self::decimal($precio), 'bruto' => self::decimal($sub), 'descuento' => '0.00', 'promociones' => [],
                'subtotal' => self::decimal($sub), 'nota' => $l['nota'], 'cocina' => (bool) $p['va_a_cocina']];
        }
        if ($total <= 0 || $total > 9999999999) throw new DomainException('El total está fuera del rango permitido. Revisa el pedido.');
        if ($total !== $esperado) throw new DomainException('Cambió el precio de un producto. Recarga y revisa el total antes de cobrar.');
        // HU-46: descuento de promoción por línea; la línea guarda su subtotal ya descontado.
        // HU-49: con la fecha, el día y la hora del pedido, y los usos de ese día para el tope.
        $promo = 0;
        $promociones = Promocion::activas();
        $contexto = PromocionService::contexto($momento);
        // Productos que se pueden regalar: activos, de una categoría activa y no agotados
        $idsRegalo = array_values(array_unique(array_filter(array_map(static fn (array $pr): int => (int) ($pr['regalo_producto_id'] ?? 0), $promociones))));
        $regalables = [];
        foreach ($idsRegalo ? Venta::productosParaCobrar($idsRegalo) : [] as $pr) {
            if ($pr['activo'] && $pr['categoria_activa'] && !$pr['agotado']) $regalables[(int) $pr['id']] = $pr;
        }
        // HU-50: no acumulables compiten, acumulables por prioridad; gana el mayor beneficio
        $evaluacion = PromocionService::evaluar($base, $promociones, $contexto,
            array_map(static fn (array $pr): int => self::centavos((string) $pr['precio']), $regalables));
        foreach ($evaluacion['lineas'] as $i => $a) {
            if ($a['descuento'] === 0) continue;
            $promo += $a['descuento'];
            $lineas[$i]['descuento'] = self::decimal($a['descuento']);
            $lineas[$i]['subtotal'] = self::decimal($base[$i]['bruto'] - $a['descuento']);
            $lineas[$i]['promociones'] = array_map(static fn (array $x): array => ['id' => (int) $x['promocion']['id'], 'etiqueta' => $x['promocion']['nombre'],
                'monto' => self::decimal($x['monto'])], $a['partes']);
        }
        // Regalo por compra: una línea más a Bs 0 (su descuento es su precio)
        $regalos = 0;
        foreach ($evaluacion['regalos'] as $r) {
            $p = $regalables[$r['producto_id']];
            $precio = self::centavos((string) $p['precio']);
            $bruto = $precio * $r['cantidad'];
            $regalos += $bruto;
            $lineas[] = ['producto_id' => (int) $p['id'], 'id' => $p['codigo'], 'nombre' => $p['nombre'], 'unidad' => Producto::UNIDADES[$p['unidad']],
                'cantidad' => $r['cantidad'], 'precio' => self::decimal($precio), 'bruto' => self::decimal($bruto), 'descuento' => self::decimal($bruto),
                'promociones' => [['id' => (int) $r['promocion']['id'], 'etiqueta' => $r['promocion']['nombre'], 'monto' => self::decimal($bruto)]], 'regalo' => true,
                'subtotal' => '0.00', 'nota' => '', 'cocina' => (bool) $p['va_a_cocina']];
        }
        if ($promo !== $promoEsperada || $regalos !== $regalosEsperados) throw new DomainException('Cambió una promoción. Recarga el punto de venta y revisa el total antes de cobrar.');
        $subtotal = $total - $promo;
        if ($subtotal < 1) throw new DomainException('Con las promociones el pedido queda en Bs 0. Revisa las promociones antes de cobrar.');
        return ['lineas' => $lineas, 'bruto' => $total, 'promo' => $promo, 'regalos' => $regalos, 'subtotal' => $subtotal];
    }

    /** Descuento manual (HU-06): el permiso de un solo uso que entregó el PIN del administrador. */
    public static function permisoDescuento(int $descuento, string $token, int $subtotal): ?array
    {
        if ($descuento <= 0) return null;
        $permiso = AutorizacionService::revisar($token, 'descuento');
        if (self::centavos($permiso['monto']) !== $descuento) throw new AutorizacionInvalida('El descuento no coincide con el autorizado. Pide el PIN del administrador otra vez.');
        if ($descuento >= $subtotal) throw new AutorizacionInvalida('El descuento debe ser menor que el subtotal del pedido.');
        return $permiso;
    }

    public static function cobrar(array $d): array
    {
        $uuid = self::uuid($d['uuid'] ?? '');
        $tipo = $d['tipo'] ?? '';
        $mesa = $d['mesa'] ?? '';
        if (!in_array($tipo, ['mesa', 'llevar'], true) || ($tipo === 'mesa' && (!is_string($mesa) || !preg_match('/^[1-9]\d?$/D', $mesa)))) {
            throw new DomainException('Elige mesa con número de 1 a 99, o para llevar.');
        }
        $mesa = $tipo === 'mesa' ? $mesa : null;
        $cantidades = self::cantidades($d['lineas'] ?? []);
        $exacto = ($d['exacto'] ?? '') === '1';
        $recibido = $exacto ? null : self::centavos($d['recibido'] ?? null);
        $esperado = self::centavos($d['total_esperado'] ?? null);
        $cajaId = filter_var($d['caja_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$cajaId || $cajaId < 1) throw new DomainException('Recarga el punto de venta para revisar la caja.');
        $descuento = ($d['descuento'] ?? '') === '' ? 0 : self::centavos($d['descuento']);
        $token = is_string($d['autorizacion'] ?? null) ? $d['autorizacion'] : '';
        $promoEsperada = ($d['promociones'] ?? '') === '' ? 0 : self::centavos($d['promociones']);
        $regalosEsperados = ($d['regalos'] ?? '') === '' ? 0 : self::centavos($d['regalos']);
        $clientePedido = ClienteService::normalizar($d['cliente_doc'] ?? '0', $d['cliente_nombre'] ?? '', $d['cliente_tipo'] ?? 'NIT');
        $datosHash = [$cajaId, $tipo, $mesa, $cantidades, $exacto, $recibido, $esperado, $descuento, $promoEsperada, $regalosEsperados];
        if ($clientePedido['doc'] !== '0') $datosHash[] = $clientePedido;
        // HU-36: el reintento de una venta sin red repite su hora; otra hora es otro pedido
        $momentoPedido = is_string($d['momento'] ?? null) ? $d['momento'] : null;
        if ($momentoPedido !== null) $datosHash[] = ['momento' => $momentoPedido];
        $hash = hash('sha256', json_encode($datosHash, JSON_THROW_ON_ERROR));
        $gastar = false;
        $factura = DB::transaccion(static function () use ($uuid, $hash, $tipo, $mesa, $cantidades, $exacto, $recibido, $esperado, $cajaId, $descuento, $token, $promoEsperada, $regalosEsperados, $clientePedido, $momentoPedido, &$gastar): array {
            // Serializa cobros y cierre sobre la misma fila, y evita cambiar de caja silenciosamente.
            $caja = Venta::cajaParaCobrar($cajaId);
            $previa = Venta::porUuid($uuid);
            if ($previa) {
                if ((int) $previa['usuario_id'] !== Auth::id() || !hash_equals($previa['solicitud_hash'], $hash)) {
                    throw new DomainException('Ese identificador ya pertenece a otro pedido. Recarga y revisa las ventas.');
                }
                return Factura::deVenta((int) $previa['id']);
            }
            if (!$caja || $caja['estado'] !== 'abierta') throw new DomainException('La caja del pedido ya no está abierta. Revisa la caja antes de cobrar.');
            if (!puede('ventas.todas') && (int) $caja['cajero_id'] !== Auth::id()) throw new DomainException('La caja abierta pertenece a otro cajero.');
            $vendida = self::momentoDeVenta($momentoPedido, Reloj::ahora(), $caja);
            $armado = self::armarLineas($cantidades, $vendida, $esperado, $promoEsperada, $regalosEsperados);
            $permiso = self::permisoDescuento($descuento, $token, $armado['subtotal']);
            $total = $armado['subtotal'] - ($permiso !== null ? $descuento : 0);
            $rec = $exacto ? $total : $recibido;
            if ($rec < $total) throw new DomainException('El efectivo recibido no cubre el total.');
            $id = self::emitir(['uuid' => $uuid, 'hash' => $hash, 'caja_id' => $cajaId, 'tipo' => $tipo, 'mesa' => $mesa, 'lineas' => $armado['lineas'],
                'promo' => $armado['promo'] + $armado['regalos'], 'subtotal' => $armado['subtotal'], 'descuento' => $permiso !== null ? $descuento : 0, 'permiso' => $permiso,
                'total' => $total, 'recibido' => $rec, 'cliente' => $clientePedido, 'vendida' => $vendida]);
            $gastar = $permiso !== null;
            return Factura::buscar($id);
        });
        // Venta guardada: el permiso ya no sirve para otra
        if ($gastar) AutorizacionService::consumir($token);
        return $factura;
    }

    /**
     * Guarda la venta con sus líneas, promociones, pago y factura (HU-57). Va dentro de
     * la transacción de quien cobra, con la caja ya bloqueada. Devuelve el id de la factura.
     *
     * @param array{uuid: string, hash: string, caja_id: int, tipo: string, mesa: ?string, lineas: list<array>, promo: int,
     *              subtotal: int, descuento: int, permiso: ?array, total: int, recibido: int, cliente: array, vendida: DateTimeImmutable,
     *              snapshot?: array, bitacora?: array} $v montos en centavos
     */
    public static function emitir(array $v): int
    {
        // La configuración queda estable hasta confirmar la emisión.
        $fiscal = DB::fila('SELECT * FROM parametros_fiscales WHERE id = 1 FOR UPDATE');
        if (!$fiscal || (int) $fiscal['numero_inicial'] < 1) throw new DomainException('Configura los parámetros fiscales antes de cobrar.');
        $negocio = Negocio::obtener();
        $leyenda = Leyenda::alAzar();
        $cliente = ClienteService::resolver($v['cliente']);
        $numero = Factura::siguientes((int) $fiscal['codigo_sucursal'], (int) $fiscal['codigo_punto_venta'], (int) $fiscal['numero_inicial']);
        $ahora = Reloj::ahora();
        $fecha = Reloj::sql($ahora);
        $permiso = $v['permiso'];
        $total = $v['total'];
        $rec = $v['recibido'];
        $ventaId = Venta::crear(['uuid' => $v['uuid'], 'solicitud_hash' => $v['hash'], 'caja_id' => $v['caja_id'], 'usuario_id' => Auth::id(), 'tipo_pedido' => $v['tipo'], 'mesa' => $v['mesa'],
            'subtotal' => self::decimal($v['subtotal']), 'descuento' => self::decimal($v['descuento']), 'descuento_motivo' => $permiso['motivo'] ?? null,
            'descuento_autorizado_por' => $permiso['admin_id'] ?? null, 'total' => self::decimal($total), 'creado_en' => $fecha]);
        foreach ($v['lineas'] as $l) {
            $detalleId = Venta::detalle($ventaId, $l);
            foreach ($l['promociones'] as $pr) Venta::promocion($ventaId, $detalleId, $pr, $pr['monto']);
        }
        Venta::pago($ventaId, self::decimal($total), self::decimal($rec), self::decimal($rec - $total));
        $s = ['pedido' => str_pad((string) $ventaId, 3, '0', STR_PAD_LEFT), 'factura' => $numero, 'tipo' => $v['tipo'], 'mesa' => $v['mesa'],
            'cliente' => $cliente, 'cajero' => Auth::usuario()['nombre'], 'negocio' => $negocio, 'fiscal' => $fiscal,
            'cuf' => Cuf::generar($negocio, $fiscal, $numero, $ahora), 'fecha_emision' => $fecha, 'fecha_larga' => $ahora->format('d/m/Y H:i'),
            'lineas' => $v['lineas'], 'promociones' => self::decimal($v['promo']), 'subtotal' => self::decimal($v['subtotal']), 'descuento' => self::decimal($v['descuento']), 'total' => self::decimal($total),
            'literal' => NumeroALetras::convertir($total),
            'recibido' => self::decimal($rec), 'cambio' => self::decimal($rec - $total), 'leyenda' => $leyenda['texto']] + ($v['snapshot'] ?? []);
        $id = Factura::crear($ventaId, $s, (int) $leyenda['id']);
        Bitacora::registrar('venta.cobro', 'ventas', $ventaId, ['factura_id' => $id, 'total' => $s['total'], 'metodo' => 'efectivo', 'leyenda_id' => $leyenda['id']]
            + ($v['promo'] > 0 ? ['promociones' => $s['promociones']] : [])
            // HU-36: vendida sin red y enviada después
            + ($ahora->getTimestamp() - $v['vendida']->getTimestamp() >= 60 ? ['vendida_sin_red' => $v['vendida']->format('d/m/Y H:i:s')] : [])
            + ($v['bitacora'] ?? []));
        if ($permiso !== null) {
            Bitacora::registrar('venta.descuento_manual', 'ventas', $ventaId, ['factura_id' => $id, 'monto' => $s['descuento'], 'motivo' => $permiso['motivo'],
                'subtotal' => $s['subtotal'], 'total' => $s['total'], 'autorizado_por' => $permiso['admin']]);
        }
        return $id;
    }
}
