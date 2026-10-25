<?php

declare(strict_types=1);

namespace Tests\Integracion;

use App\Core\DB;
use App\Core\Reloj;
use App\Core\Respuesta;
use App\Models\Bitacora;
use App\Services\ArqueoService;
use Tests\CasoPrueba;

/**
 * HU-35: el mesero envía pedidos a la cuenta de una mesa; cada pedido llega a caja con su
 * comanda y sus promociones a la hora del pedido. La factura sale una sola vez, al cerrar
 * y cobrar la cuenta (el mesero o caja), en la caja abierta.
 */
final class CuentasMesaTest extends CasoPrueba
{
    private int $caja;
    private int $majadito;   // Bs 40, va a cocina
    private int $moco;       // Bs 10

    protected function setUp(): void
    {
        parent::setUp();
        Reloj::fijar('2026-10-22 12:00:00');
        $this->majadito = (int) DB::valor("SELECT id FROM productos WHERE codigo = 'PL-01'");
        $this->moco = (int) DB::valor("SELECT id FROM productos WHERE codigo = 'RE-01'");
        $this->entrarComo('mariela', 'pin');
        $this->post('/caja/abrir', ['turno_id' => (int) DB::valor("SELECT id FROM turnos WHERE clave = 'mediodia'"), 'fondo' => '200']);
        $this->caja = (int) DB::valor("SELECT id FROM cajas WHERE estado = 'abierta'");
        $this->entrarComo('luis');
    }

    private function uuid(): string
    {
        return '12345678-1234-4123-a123-' . bin2hex(random_bytes(6));
    }

    /** @param array<int, int> $cantidades producto => cantidad */
    private function ronda(string $mesa, array $cantidades, string $bruto, string $promociones = '0.00', ?string $uuid = null): Respuesta
    {
        $lineas = [];
        foreach ($cantidades as $id => $n) {
            $lineas[] = ['id' => $id, 'cantidad' => $n, 'nota' => $id === $this->majadito ? 'sin cebolla' : ''];
        }
        return $this->post('/cuentas/rondas', [
            'uuid' => $uuid ?? $this->uuid(), 'mesa' => $mesa, 'lineas' => json_encode($lineas),
            'total_esperado' => $bruto, 'promociones' => $promociones, 'regalos' => '0.00',
            'momento' => Reloj::ahora()->format('Y-m-d\TH:i:s'),
        ]);
    }

    private function cuenta(string $mesa = '3'): array
    {
        return DB::fila('SELECT * FROM cuentas WHERE mesa = ? ORDER BY id DESC LIMIT 1', [$mesa]);
    }

    private function cerrar(int $cuenta, string $total, array $extra = []): Respuesta
    {
        return $this->post('/cuentas/' . $cuenta . '/cerrar', $extra + ['uuid' => $this->uuid(), 'exacto' => '1', 'total_esperado' => $total]);
    }

    private function lista(): array
    {
        return json_decode($this->get('/cuentas')->cuerpo, true)['cuentas'];
    }

    /**
     * Escenario 1: Enviar pedido
     *   Dado estoy en mi celular con sesión de Mesero
     *   Cuando armo un pedido para la mesa 3 y lo envío
     *   Entonces el pedido aparece en caja pendiente de cobro
     */
    public function testElPedidoDeLaMesaLlegaACajaPendienteDeCobro(): void
    {
        $this->assertStringContainsString('id="m-enviar"', $this->get('/mesero')->cuerpo);

        $r = $this->ronda('3', [$this->majadito => 2, $this->moco => 1], '90.00');

        $this->assertSame(200, $r->estado, $r->cuerpo);
        $this->assertSame(['cuenta' => (int) $this->cuenta()['id'], 'mesa' => '3', 'numero' => 1], array_intersect_key(json_decode($r->cuerpo, true), ['cuenta' => 0, 'mesa' => 0, 'numero' => 0]));
        $this->assertSame(0, (int) DB::valor('SELECT COUNT(*) FROM ventas'));   // todavía no es una venta

        $this->entrarComo('mariela', 'pin');
        $this->assertStringContainsString('id="ver-mesas"', $this->get('/pos')->cuerpo);
        [$cuenta] = $this->lista();
        $this->assertSame(['3', 'abierta', '90.00', 1, 'Luis Fernando Roca'], [$cuenta['mesa'], $cuenta['estado'], $cuenta['total'], $cuenta['nuevas'], $cuenta['abierta_por']]);
        $this->assertSame([['nombre' => 'Majadito de charque', 'cantidad' => 2, 'nota' => 'sin cebolla'], ['nombre' => 'Mocochinchi', 'cantidad' => 1, 'nota' => '']],
            array_map(static fn (array $l): array => ['nombre' => $l['nombre'], 'cantidad' => $l['cantidad'], 'nota' => $l['nota']], $cuenta['rondas'][0]['lineas']));
        $this->assertCount(1, Bitacora::ultimos('cuenta.apertura'));
        $this->assertCount(1, Bitacora::ultimos('cuenta.ronda'));
    }

    public function testCadaPedidoSeSumaALaCuentaYCajaImprimeSuComanda(): void
    {
        $this->ronda('3', [$this->majadito => 1], '40.00');
        $segunda = json_decode($this->ronda('3', [$this->moco => 2], '20.00')->cuerpo, true);

        $this->assertSame(2, $segunda['numero']);
        $this->assertSame(1, (int) DB::valor('SELECT COUNT(*) FROM cuentas'));

        // El mesero puede ver la comanda, pero solo caja la marca como impresa
        $this->assertStringStartsWith('%PDF-', $this->get($segunda['comanda'])->cuerpo);
        $this->entrarComo('mariela', 'pin');
        $this->assertSame(2, $this->lista()[0]['nuevas']);
        $pdf = $this->get($segunda['comanda']);
        $this->assertSame('application/pdf', $pdf->cabeceras['Content-Type']);
        $this->assertSame('inline; filename="Comanda-mesa-3-pedido-2.pdf"', $pdf->cabeceras['Content-Disposition']);
        [$cuenta] = $this->lista();
        $this->assertSame(1, $cuenta['nuevas']);
        $this->assertSame('60.00', $cuenta['total']);
    }

    /**
     * Escenario: Promociones a la hora de cada pedido
     *   Dado «Almuerzo» da 10 % al Majadito hasta las 13:00
     *   Cuando la mesa pide un Majadito a las 12:50 y otro a las 13:10
     *   Entonces el primero lleva el descuento y el segundo no, y la factura cobra Bs 76
     */
    public function testLasPromocionesValenALaHoraDeCadaPedido(): void
    {
        $promo = DB::insertar("INSERT INTO promociones (nombre, valor_tipo, valor, producto_id, activo) VALUES ('Almuerzo', 'porcentaje', '10.00', ?, 1)", [$this->majadito]);
        DB::consulta("UPDATE promociones SET hora_desde = '11:00:00', hora_hasta = '13:00:00' WHERE id = ?", [$promo]);

        Reloj::fijar('2026-10-22 12:50:00');
        $this->entrarComo('luis');   // la sesión del mesero vence por inactividad
        $this->assertSame(200, $this->ronda('3', [$this->majadito => 1], '40.00', '4.00')->estado);
        Reloj::fijar('2026-10-22 13:10:00');
        $this->entrarComo('luis');
        $this->assertSame(422, $this->ronda('3', [$this->majadito => 1], '40.00', '4.00')->estado);   // ya terminó
        $this->assertSame(200, $this->ronda('3', [$this->majadito => 1], '40.00')->estado);

        Reloj::fijar('2026-10-22 13:30:00');
        $this->entrarComo('luis');
        $r = $this->cerrar((int) $this->cuenta()['id'], '76.00');

        $this->assertSame(200, $r->estado, $r->cuerpo);
        $this->assertSame(['76.00', '76.00'], [DB::valor('SELECT subtotal FROM ventas'), DB::valor('SELECT total FROM ventas')]);
        $this->assertSame(['4.00', '0.00'], array_column(DB::filas('SELECT descuento FROM detalle_venta ORDER BY id'), 'descuento'));
        $this->assertSame(1, (int) DB::valor('SELECT COUNT(*) FROM venta_promociones WHERE promocion_id = ?', [$promo]));
    }

    /**
     * Escenario: El mesero cierra la cuenta y cobra
     *   Dado la mesa 3 pidió dos veces
     *   Cuando el mesero cierra la cuenta y cobra en efectivo
     *   Entonces sale una sola factura con los dos pedidos, en la caja abierta, y la mesa queda libre
     */
    public function testElMeseroCierraYCobraEnLaCajaAbierta(): void
    {
        $this->ronda('3', [$this->majadito => 2], '80.00');
        $this->ronda('3', [$this->moco => 1], '10.00');
        $cuenta = (int) $this->cuenta()['id'];

        $r = $this->cerrar($cuenta, '90.00', ['exacto' => '0', 'recibido' => '100']);

        $this->assertSame(200, $r->estado, $r->cuerpo);
        $this->assertSame('10.00', json_decode($r->cuerpo, true)['cambio']);
        $venta = DB::fila('SELECT * FROM ventas');
        $luis = (int) DB::valor("SELECT id FROM usuarios WHERE usuario = 'luis'");
        $this->assertSame([$this->caja, $luis, 'mesa', '3', '90.00'], [(int) $venta['caja_id'], (int) $venta['usuario_id'], $venta['tipo_pedido'], $venta['mesa'], $venta['total']]);
        $this->assertSame(2, (int) DB::valor('SELECT COUNT(*) FROM detalle_venta'));
        $this->assertSame(1, (int) DB::valor('SELECT COUNT(*) FROM facturas'));
        $this->assertSame('cerrada', $this->cuenta()['estado']);
        $this->assertSame((int) $venta['id'], (int) $this->cuenta()['venta_id']);
        // El efectivo que cobró el mesero lo espera el arqueo de la caja (200 de fondo + 90)
        $this->assertSame(290.0, ArqueoService::esperado(DB::fila('SELECT * FROM cajas WHERE id = ?', [$this->caja])));
        $this->assertStringStartsWith('%PDF-', $this->get('/cuentas/' . $cuenta . '/factura')->cuerpo);
        $this->assertCount(1, Bitacora::ultimos('cuenta.cierre'));

        // La mesa queda libre: el próximo pedido abre otra cuenta
        $this->ronda('3', [$this->moco => 1], '10.00');
        $this->assertNotSame($cuenta, (int) $this->cuenta()['id']);
    }

    public function testCajaTambienCobraLaCuentaYPuedeDarDescuento(): void
    {
        $this->ronda('5', [$this->majadito => 2], '80.00');
        $cuenta = (int) $this->cuenta('5')['id'];

        // El mesero no aplica descuentos manuales
        $r = $this->cerrar($cuenta, '80.00', ['descuento' => '10.00', 'autorizacion' => 'x']);
        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString('El descuento manual se aplica en caja.', $r->cuerpo);

        $this->entrarComo('mariela', 'pin');
        $token = json_decode($this->post('/autorizaciones', ['accion' => 'descuento', 'pin' => '1234', 'monto' => '10', 'motivo' => 'Demora en cocina'])->cuerpo, true)['token'];
        $r = $this->cerrar($cuenta, '80.00', ['descuento' => '10.00', 'autorizacion' => $token]);

        $this->assertSame(200, $r->estado, $r->cuerpo);
        $this->assertSame(['70.00', '10.00'], [DB::valor('SELECT total FROM ventas'), DB::valor('SELECT descuento FROM ventas')]);
        $this->assertCount(1, Bitacora::ultimos('venta.descuento_manual'));
    }

    public function testUnCobroRepetidoNoFacturaDosVeces(): void
    {
        $this->ronda('3', [$this->majadito => 1], '40.00');
        $cuenta = (int) $this->cuenta()['id'];
        $datos = ['uuid' => $this->uuid(), 'exacto' => '1', 'total_esperado' => '40.00'];

        $primero = $this->post('/cuentas/' . $cuenta . '/cerrar', $datos);
        $segundo = $this->post('/cuentas/' . $cuenta . '/cerrar', $datos);

        $this->assertSame(200, $segundo->estado, $segundo->cuerpo);
        $this->assertSame(json_decode($primero->cuerpo, true)['numero'], json_decode($segundo->cuerpo, true)['numero']);
        $this->assertSame(1, (int) DB::valor('SELECT COUNT(*) FROM facturas'));
        // Otro cobro de la misma cuenta ya cerrada se rechaza
        $this->assertStringContainsString('ya se cerró', $this->cerrar($cuenta, '40.00')->cuerpo);
    }

    public function testUnPedidoRepetidoNoSeSumaDosVeces(): void
    {
        $uuid = $this->uuid();
        $this->ronda('3', [$this->majadito => 1], '40.00', '0.00', $uuid);
        $r = $this->ronda('3', [$this->majadito => 1], '40.00', '0.00', $uuid);

        $this->assertSame(200, $r->estado, $r->cuerpo);
        $this->assertSame(1, (int) DB::valor('SELECT COUNT(*) FROM cuenta_rondas'));
    }

    public function testSiLlegaOtroPedidoMientrasSeCobraSeRevisaElTotal(): void
    {
        $this->ronda('3', [$this->majadito => 1], '40.00');
        $this->ronda('3', [$this->moco => 1], '10.00');

        $r = $this->cerrar((int) $this->cuenta()['id'], '40.00');

        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString('Cambiaron los pedidos de la mesa 3', $r->cuerpo);
        $this->assertSame('abierta', $this->cuenta()['estado']);
    }

    public function testCancelarUnPedido(): void
    {
        $propia = json_decode($this->ronda('3', [$this->majadito => 1], '40.00')->cuerpo, true);
        $otro = DB::insertar("INSERT INTO usuarios (nombre, usuario, password_hash, rol) VALUES ('Rosa Mesera', 'rosa', ?, 'mesero')",
            [DB::valor("SELECT password_hash FROM usuarios WHERE usuario = 'luis'")]);
        $this->entrarComo('rosa');
        $ajena = json_decode($this->ronda('3', [$this->moco => 1], '10.00')->cuerpo, true);

        // Rosa no cancela el pedido de Luis
        $r = $this->post('/cuentas/rondas/' . $propia['id'] . '/cancelar', ['motivo' => 'Se equivocó']);
        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString('Solo puedes cancelar los pedidos que enviaste', $r->cuerpo);
        $this->assertSame(422, $this->post('/cuentas/rondas/' . $ajena['id'] . '/cancelar', ['motivo' => ''])->estado);   // sin motivo
        $this->assertSame(200, $this->post('/cuentas/rondas/' . $ajena['id'] . '/cancelar', ['motivo' => 'El cliente se arrepintió'])->estado);
        $this->assertSame('abierta', $this->cuenta()['estado']);

        // Caja cancela cualquiera; sin pedidos, la cuenta se cancela y la mesa queda libre
        $this->entrarComo('mariela', 'pin');
        $this->assertSame(200, $this->post('/cuentas/rondas/' . $propia['id'] . '/cancelar', ['motivo' => 'Mesa se fue'])->estado);
        $this->assertSame('cancelada', $this->cuenta()['estado']);
        $this->assertCount(2, Bitacora::ultimos('cuenta.cancelacion'));
        $this->assertGreaterThan(0, $otro);
    }

    public function testSinCajaAbiertaNoSeTomanPedidos(): void
    {
        DB::consulta("UPDATE cajas SET estado = 'cerrada' WHERE id = ?", [$this->caja]);

        $r = $this->ronda('3', [$this->majadito => 1], '40.00');

        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString('No hay una caja abierta', $r->cuerpo);
        $this->assertStringContainsString('Sin caja abierta', $this->get('/mesero')->cuerpo);
    }

    public function testLaFacturaDeUnaMesaNoRepiteLasComandas(): void
    {
        $this->ronda('3', [$this->majadito => 1], '40.00');
        $this->cerrar((int) $this->cuenta()['id'], '40.00');
        $factura = (int) DB::valor('SELECT id FROM facturas');

        $this->entrarComo('admin');
        $this->assertStringContainsString('las comandas salieron con cada pedido (1)', $this->get('/facturas/' . $factura)->cuerpo);
        $r = $this->get('/facturas/' . $factura . '/pdf', ['destino' => 'cocina']);
        $this->assertSame(422, $r->estado);
    }

    /**
     * Escenario 2: Permisos
     *   Dado soy Mesero
     *   Cuando reviso mis opciones
     *   Entonces no puedo cobrar ni ver reportes
     */
    public function testElMeseroNoCobraEnCajaNiVeReportes(): void
    {
        $html = $this->get('/mesero')->cuerpo;
        $this->assertStringContainsString('href="/mesero"', $html);
        $this->assertStringNotContainsString('href="/pos"', $html);
        $this->assertStringNotContainsString('href="/reportes"', $html);

        $this->assertSame(403, $this->get('/pos')->estado);
        $this->assertSame(403, $this->post('/pos/cobrar', ['uuid' => $this->uuid()])->estado);
        $this->assertSame(403, $this->get('/reportes')->estado);
        $this->assertSame(403, $this->get('/caja')->estado);
        $this->assertSame(403, $this->get('/ventas')->estado);
        // El cajero cobra en el POS, pero no toma pedidos de mesa
        $this->entrarComo('mariela', 'pin');
        $this->assertSame(403, $this->ronda('3', [$this->majadito => 1], '40.00')->estado);
    }

    public function testLaMesaSeEligeDel1Al12UOtra(): void
    {
        $html = $this->get('/mesero')->cuerpo;
        $this->assertSame(12, substr_count($html, 'class="mesa-opcion"'));
        $this->assertStringContainsString('id="m-mesa"', $html);
        $this->assertSame(422, $this->ronda('0', [$this->majadito => 1], '40.00')->estado);
        $this->assertSame(200, $this->ronda('25', [$this->majadito => 1], '40.00')->estado);

        $this->entrarComo('mariela', 'pin');
        $this->assertSame(12, substr_count($this->get('/pos')->cuerpo, 'class="mesa-opcion"'));
    }
}
