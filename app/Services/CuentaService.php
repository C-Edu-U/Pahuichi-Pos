<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Reloj;
use App\Models\Bitacora;
use App\Models\Cuenta;
use App\Models\Factura;
use App\Models\Venta;
use DomainException;

/**
 * Cuentas por mesa (HU-35, RF-35).
 * - El mesero envía pedidos (rondas) a la cuenta de una mesa; la primera la abre. Cada
 *   ronda llega a caja para su comanda y sus promociones valen a la hora de la ronda.
 * - Al cerrar la cuenta (mesero o caja) sale una sola factura con todas las rondas; la
 *   venta queda en la caja abierta, así el arqueo espera ese efectivo.
 * - Sin caja abierta no se toman pedidos ni se cobran cuentas (sin caja no se vende).
 * - Una ronda o un cobro repetidos por un reintento llevan el mismo uuid: no se duplican.
 */
final class CuentaService
{
    private static function cajaAbierta(): ?array
    {
        // Bloquea la fila de la caja: rondas, cobros y cierre de caja van en fila
        return DB::fila("SELECT * FROM cajas WHERE estado = 'abierta' FOR UPDATE");
    }

    private static function mesa(mixed $mesa): string
    {
        if (!is_string($mesa) || !preg_match('/^[1-9]\d?$/D', $mesa)) throw new DomainException('Elige la mesa, de 1 a 99.');
        return $mesa;
    }

    /** Ronda nueva para la mesa; abre su cuenta si no hay una abierta. Devuelve la ronda. */
    public static function enviarRonda(array $d): array
    {
        $uuid = VentaService::uuid($d['uuid'] ?? '');
        $mesa = self::mesa($d['mesa'] ?? '');
        $cantidades = VentaService::cantidades($d['lineas'] ?? []);
        $esperado = VentaService::centavos($d['total_esperado'] ?? null);
        $promoEsperada = ($d['promociones'] ?? '') === '' ? 0 : VentaService::centavos($d['promociones']);
        $regalosEsperados = ($d['regalos'] ?? '') === '' ? 0 : VentaService::centavos($d['regalos']);
        $momento = is_string($d['momento'] ?? null) ? $d['momento'] : null;
        $hash = hash('sha256', json_encode([$mesa, $cantidades, $esperado, $promoEsperada, $regalosEsperados, $momento], JSON_THROW_ON_ERROR));

        return DB::transaccion(static function () use ($uuid, $mesa, $cantidades, $esperado, $promoEsperada, $regalosEsperados, $momento, $hash): array {
            $caja = self::cajaAbierta();
            $previa = Cuenta::rondaPorUuid($uuid);
            if ($previa !== null) {
                if ((int) $previa['usuario_id'] !== Auth::id() || !hash_equals($previa['solicitud_hash'], $hash)) {
                    throw new DomainException('Ese identificador ya pertenece a otro pedido. Recarga y revisa las mesas.');
                }
                return self::resumenRonda($previa);
            }
            if ($caja === null) throw new DomainException('No hay una caja abierta: avisa a caja antes de tomar pedidos.');

            $ahora = Reloj::ahora();
            $pedida = VentaService::momentoDeVenta($momento, $ahora, $caja);
            $armado = VentaService::armarLineas($cantidades, $pedida, $esperado, $promoEsperada, $regalosEsperados);

            $cuenta = Cuenta::abiertaDeMesa($mesa, true);
            if ($cuenta === null) {
                $cuentaId = Cuenta::abrir($mesa, (int) Auth::id(), Reloj::sql($ahora));
                Bitacora::registrar('cuenta.apertura', 'cuentas', $cuentaId, ['mesa' => $mesa]);
                $cuenta = Cuenta::buscar($cuentaId);
            }
            $numero = Cuenta::siguienteRonda((int) $cuenta['id']);
            $promo = $armado['promo'] + $armado['regalos'];
            $rondaId = Cuenta::crearRonda([
                'cuenta_id' => (int) $cuenta['id'], 'numero' => $numero, 'uuid' => $uuid, 'hash' => $hash, 'usuario_id' => (int) Auth::id(),
                'bruto' => VentaService::decimal($armado['bruto'] + $armado['regalos']), 'promociones' => VentaService::decimal($promo),
                'subtotal' => VentaService::decimal($armado['subtotal']), 'pedida_en' => Reloj::sql($pedida), 'creado_en' => Reloj::sql($ahora),
            ]);
            foreach ($armado['lineas'] as $l) {
                Cuenta::linea($rondaId, $l);
            }
            Bitacora::registrar('cuenta.ronda', 'cuentas', (int) $cuenta['id'], [
                'mesa' => $mesa, 'ronda' => $numero, 'total' => VentaService::decimal($armado['subtotal']),
            ] + ($promo > 0 ? ['promociones' => VentaService::decimal($promo)] : []));
            return self::resumenRonda(Cuenta::ronda($rondaId));
        });
    }

    private static function resumenRonda(array $r): array
    {
        $cuenta = Cuenta::buscar((int) $r['cuenta_id']);
        return ['id' => (int) $r['id'], 'cuenta' => (int) $r['cuenta_id'], 'mesa' => $cuenta['mesa'], 'numero' => (int) $r['numero'],
            'subtotal' => $r['subtotal'], 'comanda' => '/cuentas/rondas/' . (int) $r['id'] . '/comanda'];
    }

    /** Cancela un pedido de una cuenta abierta. El mesero, solo los que envió; caja, cualquiera. */
    public static function cancelarRonda(int $rondaId, string $motivo): void
    {
        $motivo = trim($motivo);
        if (mb_strlen($motivo) < 3 || mb_strlen($motivo) > 120) throw new DomainException('Escribe el motivo de la cancelación (de 3 a 120 caracteres).');

        DB::transaccion(static function () use ($rondaId, $motivo): void {
            $ronda = Cuenta::ronda($rondaId, true) ?? throw new DomainException('Ese pedido no existe.');
            $cuenta = Cuenta::buscar((int) $ronda['cuenta_id'], true);
            if ($cuenta['estado'] !== 'abierta') throw new DomainException('La cuenta de la mesa ' . $cuenta['mesa'] . ' ya se cerró.');
            if ($ronda['estado'] !== 'enviada') throw new DomainException('Ese pedido ya estaba cancelado.');
            if (Auth::usuario()['rol'] === 'mesero' && (int) $ronda['usuario_id'] !== Auth::id()) {
                throw new DomainException('Solo puedes cancelar los pedidos que enviaste. Pídeselo a caja.');
            }
            $ahora = Reloj::sql();
            Cuenta::cancelarRonda($rondaId, (int) Auth::id(), $motivo, $ahora);
            // Sin pedidos, la cuenta se cancela y la mesa queda libre
            $cancelada = Cuenta::rondasActivas((int) $cuenta['id']) === 0;
            if ($cancelada) {
                Cuenta::cancelar((int) $cuenta['id'], (int) Auth::id(), $ahora);
            }
            Bitacora::registrar('cuenta.cancelacion', 'cuentas', (int) $cuenta['id'], [
                'mesa' => $cuenta['mesa'], 'ronda' => (int) $ronda['numero'], 'total' => $ronda['subtotal'], 'motivo' => $motivo,
            ] + ($cancelada ? ['cuenta' => 'cancelada'] : []));
        });
    }

    /**
     * Cierra la cuenta y la cobra (efectivo, QR o mixto): una factura con todas sus rondas, en la caja
     * abierta. El descuento manual solo lo aplica caja (con el PIN del administrador).
     */
    public static function cerrar(int $cuentaId, array $d): array
    {
        return self::cerrarCuenta($cuentaId, null, $d);
    }

    /**
     * Cierra la cuenta abierta de una mesa (HU-35, sin red): el mesero puede haber abierto la
     * cuenta sin conexión, así que su cobro guardado la nombra por la mesa, no por su número.
     */
    public static function cerrarMesa(mixed $mesa, array $d): array
    {
        return self::cerrarCuenta(null, self::mesa($mesa), $d);
    }

    private static function cerrarCuenta(?int $cuentaId, ?string $mesa, array $d): array
    {
        $uuid = VentaService::uuid($d['uuid'] ?? '');
        // HU-39 y HU-42: la cuenta se cobra en efectivo, por QR o mixto, como en el POS
        $pagoPedido = VentaService::pagoDe($d);
        $esperado = VentaService::centavos($d['total_esperado'] ?? null);
        $descuento = ($d['descuento'] ?? '') === '' ? 0 : VentaService::centavos($d['descuento']);
        $token = is_string($d['autorizacion'] ?? null) ? $d['autorizacion'] : '';
        if ($descuento > 0 && !puede('cuentas.descuento')) throw new DomainException('El descuento manual se aplica en caja.');
        $cliente = ClienteService::normalizar($d['cliente_doc'] ?? '0', $d['cliente_nombre'] ?? '', $d['cliente_tipo'] ?? 'NIT');
        $datosHash = [$cuentaId !== null ? ['cuenta' => $cuentaId] : ['mesa' => $mesa], $pagoPedido['exacto'], $pagoPedido['recibido'], $esperado, $descuento, ...$pagoPedido['hash']];
        if ($cliente['doc'] !== '0') $datosHash[] = $cliente;
        $hash = hash('sha256', json_encode($datosHash, JSON_THROW_ON_ERROR));
        $gastar = false;

        $factura = DB::transaccion(static function () use ($cuentaId, $mesa, $uuid, $hash, $pagoPedido, $esperado, $descuento, $token, $cliente, &$gastar): array {
            $caja = self::cajaAbierta();
            $previa = Venta::porUuid($uuid);
            if ($previa !== null) {
                if ((int) $previa['usuario_id'] !== Auth::id() || !hash_equals($previa['solicitud_hash'], $hash)) {
                    throw new DomainException('Ese identificador ya pertenece a otro cobro. Recarga y revisa las mesas.');
                }
                return Factura::buscar((int) Factura::deVenta((int) $previa['id'])['id']);
            }
            if ($caja === null) throw new DomainException('No hay una caja abierta: el cobro de la cuenta va a la caja abierta.');
            // HU-20: un cajero cobra solo en su caja; el mesero cobra en la caja abierta
            if (Auth::usuario()['rol'] === 'cajero' && (int) $caja['cajero_id'] !== Auth::id()) {
                throw new DomainException('La caja abierta pertenece a otro cajero.');
            }
            $cuenta = $cuentaId !== null
                ? Cuenta::buscar($cuentaId, true) ?? throw new DomainException('Esa cuenta no existe.')
                : Cuenta::abiertaDeMesa($mesa, true) ?? throw new DomainException('La mesa ' . $mesa . ' no tiene una cuenta abierta: ya se cobró o se canceló.');
            $cuentaId = (int) $cuenta['id'];
            if ($cuenta['estado'] !== 'abierta') throw new DomainException('La cuenta de la mesa ' . $cuenta['mesa'] . ' ya se cerró.');

            $lineas = Cuenta::lineasActivas($cuentaId);
            if ($lineas === []) throw new DomainException('La cuenta de la mesa ' . $cuenta['mesa'] . ' no tiene pedidos.');
            $subtotal = 0;
            $promo = 0;
            foreach ($lineas as $l) {
                $subtotal += VentaService::centavos($l['subtotal']);
                $promo += VentaService::centavos($l['descuento']);
            }
            if ($subtotal !== $esperado) throw new DomainException('Cambiaron los pedidos de la mesa ' . $cuenta['mesa'] . '. Revisa el total antes de cobrar.');
            $permiso = VentaService::permisoDescuento($descuento, $token, $subtotal);
            $total = $subtotal - ($permiso !== null ? $descuento : 0);
            $pago = VentaService::cobrarPago($pagoPedido, $total);

            $rondas = (int) DB::valor("SELECT COUNT(*) FROM cuenta_rondas WHERE cuenta_id = ? AND estado = 'enviada'", [$cuentaId]);
            $id = VentaService::emitir(['uuid' => $uuid, 'hash' => $hash, 'caja_id' => (int) $caja['id'], 'tipo' => 'mesa', 'mesa' => $cuenta['mesa'],
                'lineas' => $lineas, 'promo' => $promo, 'subtotal' => $subtotal, 'descuento' => $permiso !== null ? $descuento : 0, 'permiso' => $permiso,
                'total' => $total, 'pago' => $pago, 'cliente' => $cliente, 'vendida' => Reloj::ahora(),
                // Las comandas ya salieron con cada ronda: los tickets de esta venta no las repiten
                'snapshot' => ['cuenta' => ['id' => $cuentaId, 'rondas' => $rondas]],
                'bitacora' => ['cuenta' => (int) $cuentaId, 'mesa' => $cuenta['mesa']]]);
            $f = Factura::buscar($id);
            Cuenta::cerrar($cuentaId, (int) $f['venta_id'], (int) Auth::id(), Reloj::sql());
            Bitacora::registrar('cuenta.cierre', 'cuentas', $cuentaId, ['mesa' => $cuenta['mesa'], 'rondas' => $rondas,
                'total' => VentaService::decimal($total), 'factura' => (int) $f['numero']]);
            $gastar = $permiso !== null;
            return $f;
        });
        if ($gastar) AutorizacionService::consumir($token);
        return $factura;
    }
}
