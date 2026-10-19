<?php

declare(strict_types=1);

namespace Tests\Integracion;

use App\Core\DB;
use App\Core\Reloj;
use App\Models\Arqueo;
use App\Models\Bitacora;
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
        DB::consulta(
            'INSERT INTO ventas (caja_id, turno_id, usuario_id, fecha, subtotal, descuento, total, metodo_pago, estado_pago, anulada, creado_en)
             VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, NOW())',
            [$this->cajaId, $this->mediodia, $this->mariela, $total, $descuento, $total, $metodo, $estado, $anulada ? 1 : 0],
        );
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
        $this->venta('efectivo', 'pagado', 1350.00);
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
        $this->venta('efectivo', 'pagado', 1000.00);
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
        $this->venta('efectivo', 'pagado', 1000.00, 20.00);
        $this->venta('qr', 'pagado', 300.00);
        $this->venta('qr', 'pendiente', 150.00);
        $this->venta('qr', 'rechazado', 80.00);
        $this->venta('efectivo', 'pagado', 500.00, 0.0, true); // anulada
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

    public function testSinConteoNoHayArqueo(): void
    {
        $this->assertRedirige('/caja/cierre', $this->get('/caja/arqueo'));
    }

    public function testElEgresoSeRestaDelEsperado(): void
    {
        $this->venta('efectivo', 'pagado', 1000.00);
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
}
