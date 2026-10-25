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
use DomainException;

/**
 * HU-57 y HU-39: cobro en efectivo o QR pendiente, con reintento idempotente.
 * HU-06: descuento manual con el permiso de un solo uso que entregó el PIN del
 * administrador (AutorizacionService); el permiso se gasta recién al guardar la venta.
 * HU-46: las promociones activas descuentan por línea (PromocionService). El navegador
 * envía cuánto descontaron en pantalla; si no coincide, una promoción cambió y no se cobra.
 * El regalo por compra entra como una línea más, con descuento igual a su precio (Bs 0).
 */
final class VentaService
{
    public static function decimal(int $centavos): string
    {
        return intdiv($centavos, 100) . '.' . str_pad((string) ($centavos % 100), 2, '0', STR_PAD_LEFT);
    }

    private static function centavos(mixed $dato): int
    {
        if (!is_string($dato) || !preg_match('/^\d{1,8}(?:[.,]\d{1,2})?$/D', $dato)) {
            throw new DomainException('Escribe un monto válido, con hasta dos decimales.');
        }
        $p = explode('.', str_replace(',', '.', $dato));
        return (int) $p[0] * 100 + (int) str_pad($p[1] ?? '', 2, '0');
    }

    public static function cobrar(array $d): array
    {
        $uuid = $d['uuid'] ?? '';
        if (!is_string($uuid) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/Di', $uuid)) {
            throw new DomainException('Recarga la pantalla para identificar el pedido.');
        }
        $tipo = $d['tipo'] ?? '';
        $mesa = $d['mesa'] ?? '';
        if (!in_array($tipo, ['mesa', 'llevar'], true) || ($tipo === 'mesa' && (!is_string($mesa) || !preg_match('/^[1-9]\d?$/D', $mesa)))) {
            throw new DomainException('Elige mesa con número de 1 a 99, o para llevar.');
        }
        $mesa = $tipo === 'mesa' ? $mesa : null;
        $lineas = $d['lineas'] ?? [];
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
        $metodo = $d['metodo'] ?? 'efectivo';
        if (!in_array($metodo, ['efectivo', 'qr'], true)) throw new DomainException('Elige efectivo o QR.');
        $qr = null;
        if ($metodo === 'qr') {
            $ref = is_string($d['comprobante'] ?? null) ? trim($d['comprobante']) : '';
            $pagador = is_string($d['pagador'] ?? null) ? trim($d['pagador']) : '';
            if (!preg_match('/^[\p{L}\p{N}\-\/_.]{1,80}$/uD', $ref)) throw new DomainException('Escribe el número de comprobante QR (hasta 80 caracteres, sin espacios).');
            if (mb_strlen($pagador) > 120) throw new DomainException('El nombre del pagador admite hasta 120 caracteres.');
            $qrId = filter_var($d['qr_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$qrId || $qrId < 1) throw new DomainException('Consulta el QR vigente antes de cobrar.');
            $foto = is_string($d['foto_qr'] ?? null) ? $d['foto_qr'] : '';
            if (strlen($foto) > 1400000) throw new DomainException('La foto del comprobante admite hasta 1 MB.');
            $bytes = $foto === '' ? null : base64_decode($foto, true);
            if ($bytes === false) throw new DomainException('No se pudo leer la foto del comprobante.');
            $fotoTipo = $bytes === null ? null : QrDiarioService::imagen($bytes, 1048576);
            $qr = ['id' => $qrId, 'monto' => self::centavos($d['qr_monto'] ?? null), 'comprobante' => $ref, 'pagador' => $pagador, 'foto' => $bytes, 'foto_tipo' => $fotoTipo];
        }
        $exacto = $metodo === 'qr' || ($d['exacto'] ?? '') === '1';
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
        if ($qr !== null) $datosHash[] = [$metodo, $qr['id'], $qr['monto'], $qr['comprobante'], $qr['pagador'], hash('sha256', $qr['foto'] ?? '')];
        if ($clientePedido['doc'] !== '0') $datosHash[] = $clientePedido;
        $hash = hash('sha256', json_encode($datosHash, JSON_THROW_ON_ERROR));
        $gastar = false;
        $factura = DB::transaccion(static function () use ($uuid, $hash, $tipo, $mesa, $cantidades, $exacto, $recibido, $esperado, $cajaId, $descuento, $token, $promoEsperada, $regalosEsperados, $clientePedido, $metodo, $qr, &$gastar): array {
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
            // HU-49: con la fecha, el día y la hora del cobro, y los usos de hoy para el tope.
            $promo = 0;
            $promociones = Promocion::activas();
            $contexto = PromocionService::contexto(Reloj::ahora());
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
            $total = $subtotal;
            $permiso = null;
            if ($descuento > 0) {
                $permiso = AutorizacionService::revisar($token, 'descuento');
                if (self::centavos($permiso['monto']) !== $descuento) throw new AutorizacionInvalida('El descuento no coincide con el autorizado. Pide el PIN del administrador otra vez.');
                if ($descuento >= $subtotal) throw new AutorizacionInvalida('El descuento debe ser menor que el subtotal del pedido.');
                $total = $subtotal - $descuento;
            }
            $ahora = Reloj::ahora();
            if ($qr !== null) {
                $vigente = QrDiarioService::estado(true);
                if (!$vigente['vigente'] || $vigente['id'] !== $qr['id']) throw new DomainException('El QR venció o fue reemplazado. Consulta el QR de hoy antes de cobrar.');
                $fijo = self::centavos($vigente['monto']);
                if ($total <= 0 || $qr['monto'] !== $total || ($fijo > 0 && $fijo !== $total)) throw new DomainException('El monto pagado por QR debe cubrir el total del pedido y respetar el importe fijo del QR, si tiene uno.');
            }
            if ($qr !== null) ComprobanteQrService::reservar($qr['comprobante'], $ahora->format('Y-m-d'));
            $rec = $exacto ? $total : $recibido;
            if ($rec < $total) throw new DomainException('El efectivo recibido no cubre el total.');
            // La configuración queda estable hasta confirmar la emisión.
            $fiscal = DB::fila('SELECT * FROM parametros_fiscales WHERE id = 1 FOR UPDATE');
            if (!$fiscal || (int) $fiscal['numero_inicial'] < 1) throw new DomainException('Configura los parámetros fiscales antes de cobrar.');
            $negocio = Negocio::obtener();
            $leyenda = Leyenda::alAzar();
            $cliente = ClienteService::resolver($clientePedido);
            $numero = Factura::siguientes((int) $fiscal['codigo_sucursal'], (int) $fiscal['codigo_punto_venta'], (int) $fiscal['numero_inicial']);
            $fecha = Reloj::sql($ahora);
            $ventaId = Venta::crear(['uuid' => $uuid, 'solicitud_hash' => $hash, 'caja_id' => $cajaId, 'usuario_id' => Auth::id(), 'tipo_pedido' => $tipo, 'mesa' => $mesa,
                'subtotal' => self::decimal($subtotal), 'descuento' => self::decimal($descuento), 'descuento_motivo' => $permiso['motivo'] ?? null,
                'descuento_autorizado_por' => $permiso['admin_id'] ?? null, 'total' => self::decimal($total), 'creado_en' => $fecha]);
            foreach ($lineas as $l) {
                $detalleId = Venta::detalle($ventaId, $l);
                foreach ($l['promociones'] as $pr) Venta::promocion($ventaId, $detalleId, $pr, $pr['monto']);
            }
            if ($qr === null) {
                Venta::pago($ventaId, self::decimal($total), self::decimal($rec), self::decimal($rec - $total));
            } else {
                $pagoId = DB::insertar("INSERT INTO pagos (venta_id, metodo, monto, recibido, cambio, estado) VALUES (?, 'qr', ?, ?, 0, 'pendiente')", [$ventaId, self::decimal($total), self::decimal($total)]);
                ComprobanteQrService::asignar($qr['comprobante'], $ahora->format('Y-m-d'), $pagoId);
                DB::insertar('INSERT INTO qr_pagos (pago_id, qr_diario_id, comprobante, pagador, foto, foto_tipo) VALUES (?, ?, ?, ?, ?, ?)', [$pagoId, $qr['id'], $qr['comprobante'], $qr['pagador'] ?: null, $qr['foto'], $qr['foto_tipo']]);
            }
            $s = ['pedido' => str_pad((string) $ventaId, 3, '0', STR_PAD_LEFT), 'factura' => $numero, 'tipo' => $tipo, 'mesa' => $mesa,
                'cliente' => $cliente, 'cajero' => Auth::usuario()['nombre'], 'negocio' => $negocio, 'fiscal' => $fiscal,
                'cuf' => Cuf::generar($negocio, $fiscal, $numero, $ahora), 'fecha_emision' => $fecha, 'fecha_larga' => $ahora->format('d/m/Y H:i'),
                'lineas' => $lineas, 'promociones' => self::decimal($promo + $regalos), 'subtotal' => self::decimal($subtotal), 'descuento' => self::decimal($descuento), 'total' => self::decimal($total),
                'literal' => NumeroALetras::convertir($total),
                'recibido' => self::decimal($rec), 'cambio' => self::decimal($rec - $total), 'leyenda' => $leyenda['texto']];
            if ($qr !== null) $s['pago_qr'] = ['estado' => 'pendiente', 'monto' => self::decimal($total), 'comprobante' => $qr['comprobante'], 'pagador' => $qr['pagador']];
            $id = Factura::crear($ventaId, $s, (int) $leyenda['id']);
            Bitacora::registrar('venta.cobro', 'ventas', $ventaId, ['factura_id' => $id, 'total' => $s['total'], 'metodo' => $metodo, 'leyenda_id' => $leyenda['id']]
                + ($qr !== null ? ['estado_pago' => 'pendiente', 'comprobante' => $qr['comprobante']] : [])
                + ($promo + $regalos > 0 ? ['promociones' => $s['promociones']] : []));
            if ($permiso !== null) {
                Bitacora::registrar('venta.descuento_manual', 'ventas', $ventaId, ['factura_id' => $id, 'monto' => $s['descuento'], 'motivo' => $permiso['motivo'],
                    'subtotal' => $s['subtotal'], 'total' => $s['total'], 'autorizado_por' => $permiso['admin']]);
                $gastar = true;
            }
            return Factura::buscar($id);
        });
        // Venta guardada: el permiso ya no sirve para otra
        if ($gastar) AutorizacionService::consumir($token);
        return $factura;
    }
}
