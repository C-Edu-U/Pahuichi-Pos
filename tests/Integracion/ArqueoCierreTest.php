<?php

declare(strict_types=1);

namespace Tests\Integracion;

use App\Core\DB;
use App\Core\Reloj;
use App\Models\Arqueo;
use App\Models\Bitacora;
use App\Models\Caja;
use App\Services\ArqueoService;
use DomainException;
use Tests\CasoPrueba;

/** HU-24: arqueo automático y resumen de cierre. */
final class ArqueoCierreTest extends CasoPrueba
{
    private int $mediodia;
    private int $cajaId;
    private int $mariela;

    protected function setUp(): void
    {
        parent::setUp();
        Reloj::fijar('2026-10-15 12:00:00');
        $this->entrarComo('mariela', 'pin');
        $this->mediodia = (int) DB::valor("SELECT id FROM turnos WHERE clave = 'mediodia'");
        $this->mariela = (int) DB::valor("SELECT id FROM usuarios WHERE usuario = 'mariela'");
        $this->post('/caja/abrir', ['turno_id' => $this->mediodia, 'fondo' => '200']);
        $this->cajaId = (int) DB::valor("SELECT id FROM cajas WHERE estado = 'abierta'");
    }

    private function venta(string $metodo, string $estado, float $total, float $descuento = 0.0, bool $anulada = false): void
    {
        $this->ventaDirecta($this->cajaId, $this->mariela, $metodo, $estado, $total, $descuento, $anulada);
    }

    private function contar(int $billetes200, int $billetes50, int $billetes10): void
    {
        $this->post('/caja/conteo', ['conteo' => [
            '200' => (string) $billetes200, '100' => '0', '50' => (string) $billetes50, '20' => '0', '10' => (string) $billetes10,
            '5' => '0', '2' => '0', '1' => '0', '0.50' => '0', '0.20' => '0', '0.10' => '0',
        ]]);
    }

    /**
     * Escenario: Arqueo con faltante
     *   Dado un fondo de 200 Bs, ventas en efectivo por 1.350 Bs y un egreso de 50 Bs
     *   Cuando cuento 1.490 Bs al cerrar
     *   Entonces el sistema muestra un faltante de 10 Bs
     */
    public function testElArqueoMuestraElFaltante(): void
    {
        $this->venta('efectivo', 'confirmado', 1350.00);
        $this->post('/caja/movimientos', ['tipo' => 'egreso', 'monto' => '50', 'motivo' => 'compra de hielo']);
        $this->contar(7, 1, 4); // 1400 + 50 + 40 = 1490

        $r = $this->get('/caja/arqueo');

        $this->assertSame(200, $r->estado);
        $this->assertStringContainsString('1.500,00', $r->cuerpo);   // efectivo esperado
        $this->assertStringContainsString('1.490,00', $r->cuerpo);   // total contado
        $this->assertStringContainsString('Faltante de Bs 10,00', $r->cuerpo);
    }

    /**
     * Escenario: QR pendiente
     *   Dado una venta por QR con pago Pendiente
     *   Cuando calculo el arqueo
     *   Entonces no suma al efectivo esperado
     */
    public function testLaVentaQrPendienteNoSuma(): void
    {
        $this->venta('efectivo', 'confirmado', 1000.00);
        $this->venta('qr', 'pendiente', 500.00);
        $this->contar(6, 0, 0); // 1200

        $r = $this->get('/caja/arqueo');

        // Esperado: 200 de fondo + 1000 de efectivo = 1200 (el QR pendiente no suma)
        $this->assertStringContainsString('1.200,00', $r->cuerpo);
        $this->assertStringContainsString('La caja cuadra', $r->cuerpo);
    }

    /**
     * Escenario: Resumen
     *   Dado confirmo el cierre
     *   Cuando veo el resumen
     *   Entonces muestra ventas, total por medio de pago, QR confirmados, pendientes y rechazados, descuentos, anulaciones y diferencia
     */
    public function testElResumenDelTurno(): void
    {
        $this->venta('efectivo', 'confirmado', 1000.00, 20.00);
        $this->venta('qr', 'confirmado', 300.00);
        $this->venta('qr', 'pendiente', 150.00);
        $this->venta('qr', 'rechazado', 80.00);
        $this->venta('efectivo', 'confirmado', 500.00, 0.0, true); // anulada
        $this->contar(6, 0, 0); // 1200

        $this->assertRedirige('/caja/arqueo', $this->post('/caja/arqueo', ['observacion' => '']));

        $caja = DB::fila('SELECT * FROM cajas WHERE id = ?', [$this->cajaId]);
        $this->assertSame('cerrada', $caja['estado']);
        $this->assertSame('2026-10-15 12:00:00', $caja['cerrada_en']);

        $a = DB::fila('SELECT * FROM arqueos WHERE caja_id = ?', [$this->cajaId]);
        $this->assertSame('1200.00', $a['efectivo_esperado']);
        $this->assertSame('0.00', $a['diferencia']);

        $resumen = json_decode((string) $a['resumen'], true);
        $this->assertSame(5, $resumen['ventas']);
        $this->assertSame('1000.00', $resumen['efectivo']);
        $this->assertSame('300.00', $resumen['qr']['confirmados']);
        $this->assertSame('150.00', $resumen['qr']['pendientes']);
        $this->assertSame('80.00', $resumen['qr']['rechazados']);
        $this->assertSame('20.00', $resumen['descuentos']);
        $this->assertSame('500.00', $resumen['anuladas']);
        $this->assertCount(1, Bitacora::ultimos('caja.arqueo'));
    }

    /**
     * Escenario: Pago mixto y descuentos de promoción
     *   Dado una venta de Bs 50 pagada 30 en efectivo y 20 por QR pendiente, con Bs 5 de promoción en una línea y Bs 3 de descuento manual
     *   Cuando confirmo el cierre
     *   Entonces solo los 30 en efectivo suman al esperado y los descuentos del resumen son Bs 8
     */
    public function testPagoMixtoYDescuentosDePromocion(): void
    {
        $venta = $this->ventaDirecta($this->cajaId, $this->mariela, 'efectivo', 'confirmado', 30.00, 3.00);
        DB::consulta('UPDATE ventas SET total = 50.00, subtotal = 53.00 WHERE id = ?', [$venta]);
        DB::insertar("INSERT INTO pagos (venta_id, metodo, monto, recibido, cambio, estado) VALUES (?, 'qr', 20.00, 20.00, 0, 'pendiente')", [$venta]);
        DB::insertar(
            "INSERT INTO detalle_venta (venta_id, producto_id, descripcion, cantidad, precio_unitario, descuento, subtotal, nota)
             VALUES (?, (SELECT id FROM productos WHERE codigo = 'PL-01'), 'Majadito de charque', 1, 40.00, 5.00, 35.00, '')",
            [$venta],
        );
        $this->contar(1, 0, 3); // 230 = 200 de fondo + 30 en efectivo

        $this->assertRedirige('/caja/arqueo', $this->post('/caja/arqueo', ['observacion' => '']));

        $a = Arqueo::porCaja($this->cajaId);
        $this->assertSame(230.00, $a['efectivo_esperado']);
        $this->assertSame(0.00, $a['diferencia']);
        $this->assertSame('8.00', $a['resumen']['descuentos']);
        $this->assertSame('20.00', $a['resumen']['qr']['pendientes']);
        $this->assertSame('50.00', $a['resumen']['total']);
    }

    /**
     * Escenario: Una venta de último momento
     *   Dado conté el efectivo y la caja cuadraba
     *   Cuando entra una venta en efectivo antes de confirmar el cierre
     *   Entonces el arqueo guardado la incluye y pide explicar el sobrante o faltante que resulta
     */
    public function testElCierreCalculaConLaCajaComoEstaAlConfirmar(): void
    {
        $this->contar(1, 0, 0); // 200: cuadra con el fondo
        $this->assertStringContainsString('La caja cuadra', $this->get('/caja/arqueo')->cuerpo);
        $this->venta('efectivo', 'confirmado', 40.00);

        try {
            (new ArqueoService())->confirmarCierre($this->cajaId);
            $this->fail('Sin observación no debía cerrar');
        } catch (DomainException $e) {
            $this->assertSame('La diferencia es de Bs 40,00. Escribe una observación para explicar el faltante.', $e->getMessage());
        }
        $this->assertSame('abierta', DB::valor('SELECT estado FROM cajas WHERE id = ?', [$this->cajaId]));

        $guardado = (new ArqueoService())->confirmarCierre($this->cajaId, 'Venta cobrada al cerrar');

        $this->assertSame(['esperado' => 240.00, 'diferencia' => -40.00], $guardado);
        $a = Arqueo::porCaja($this->cajaId);
        $this->assertSame(240.00, $a['efectivo_esperado']);
        $this->assertSame(1, $a['resumen']['ventas']);
    }

    public function testUnaCajaCerradaNoSeVuelveACerrar(): void
    {
        $this->contar(1, 0, 0);
        (new ArqueoService())->confirmarCierre($this->cajaId);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('La caja ya está cerrada.');
        (new ArqueoService())->confirmarCierre($this->cajaId);
    }

    public function testSinConteoNoHayArqueo(): void
    {
        $this->assertRedirige('/caja/cierre', $this->get('/caja/arqueo'));
    }

    public function testElEgresoSeRestaDelEsperado(): void
    {
        $this->venta('efectivo', 'confirmado', 1000.00);
        $this->post('/caja/movimientos', ['tipo' => 'egreso', 'monto' => '50', 'motivo' => 'compra de hielo']);
        $this->post('/caja/movimientos', ['tipo' => 'ingreso', 'monto' => '25', 'motivo' => 'cambio de turno']);
        $this->contar(5, 0, 0); // 1000

        $r = $this->get('/caja/arqueo');

        // 200 de fondo + 1000 de ventas + 25 de ingreso - 50 de egreso = 1175
        $this->assertStringContainsString('1.175,00', $r->cuerpo);
        $this->assertStringContainsString('Faltante de Bs 175,00', $r->cuerpo);
    }

    public function testElConteoSeGuardaEnElArqueo(): void
    {
        $this->contar(1, 2, 3);

        $a = Arqueo::porCaja($this->cajaId);
        $this->assertNotNull($a);
        $this->assertSame(330.00, $a['total_contado']);
    }

    /**
     * Escenario: Reabrir caja
     *   Dado una caja cerrada y arqueada
     *   Cuando el administrador la reabre
     *   Entonces vuelve a estar abierta y sin arqueo, para cerrar el turno otra vez
     */
    public function testElAdministradorReabreUnaCajaCerrada(): void
    {
        $this->venta('efectivo', 'confirmado', 100.00);
        $this->contar(1, 0, 10); // 300
        $this->assertRedirige('/caja/arqueo', $this->post('/caja/arqueo', ['observacion' => '']));

        $this->entrarComo('admin');
        $r = $this->post('/caja/reabrir/' . $this->cajaId);

        $this->assertRedirige('/caja', $r);
        $caja = Caja::buscar($this->cajaId);
        $this->assertNotNull($caja);
        $this->assertSame('abierta', $caja['estado']);
        $this->assertNull($caja['cerrada_en']);
        $this->assertNull(Arqueo::porCaja($this->cajaId));
        $this->assertCount(1, Bitacora::ultimos('caja.reabrir'));

        // Con la caja reabierta el cajero vuelve a contar y a cerrar el turno
        $this->entrarComo('mariela', 'pin');
        $this->contar(1, 0, 10);
        $this->assertRedirige('/caja/arqueo', $this->post('/caja/arqueo', ['observacion' => '']));
    }

    /**
     * Escenario: Limpiar historial
     *   Dado una caja cerrada con arqueo y ventas
     *   Cuando el administrador limpia el historial
     *   Entonces la caja sale del historial y sus arqueos, movimientos y ventas se conservan (RNF-20)
     */
    public function testLimpiarHistorialArchivaSinBorrar(): void
    {
        $this->venta('efectivo', 'confirmado', 100.00);
        $this->post('/caja/movimientos', ['tipo' => 'ingreso', 'monto' => '20', 'motivo' => 'cambio']);
        $this->contar(1, 0, 12); // 200 + 100 + 20 = 320
        $this->assertRedirige('/caja/arqueo', $this->post('/caja/arqueo', ['observacion' => '']));

        $this->entrarComo('admin');
        $this->assertStringContainsString('data-confirmar="¿Ocultar del historial', $this->get('/caja')->cuerpo);
        $r = $this->post('/caja/limpiar-historial');

        $this->assertRedirige('/caja', $r);
        $caja = Caja::buscar($this->cajaId);
        $this->assertNotNull($caja);
        $this->assertSame(1, (int) $caja['archivada']);
        $this->assertNotNull(Arqueo::porCaja($this->cajaId));
        $this->assertSame(1, (int) DB::valor('SELECT COUNT(*) FROM ventas WHERE caja_id = ?', [$this->cajaId]));
        $this->assertSame(1, (int) DB::valor('SELECT COUNT(*) FROM movimientos_caja WHERE caja_id = ?', [$this->cajaId]));
        $this->assertNotContains($this->cajaId, array_map('intval', array_column(Caja::ultimas(), 'id')));
        $registro = Bitacora::ultimos('caja.limpiar_historial');
        $this->assertCount(1, $registro);
        $this->assertStringContainsString('"archivadas":1', (string) $registro[0]['detalle']);
    }
}
