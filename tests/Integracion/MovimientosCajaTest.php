<?php

declare(strict_types=1);

namespace Tests\Integracion;

use App\Core\DB;
use App\Core\Reloj;
use App\Models\Bitacora;
use App\Models\MovimientoCaja;
use Tests\CasoPrueba;

/** HU-22: ingresos y egresos de efectivo durante el turno. */
final class MovimientosCajaTest extends CasoPrueba
{
    private int $mediodia;

    protected function setUp(): void
    {
        parent::setUp();
        Reloj::fijar('2026-10-15 12:00:00');
        $this->entrarComo('mariela', 'pin');
        $this->mediodia = (int) DB::valor("SELECT id FROM turnos WHERE clave = 'mediodia'");
        $this->post('/caja/abrir', ['turno_id' => $this->mediodia, 'fondo' => '300']);
    }

    private function idCaja(): int
    {
        return (int) DB::valor("SELECT id FROM cajas WHERE estado = 'abierta'");
    }

    /**
     * Escenario: Registrar egreso
     *   Dado una caja abierta
     *   Cuando registro un egreso de 50 Bs con motivo «compra de hielo»
     *   Entonces queda guardado con responsable, fecha y hora
     */
    public function testRegistrarEgreso(): void
    {
        $r = $this->post('/caja/movimientos', ['tipo' => 'egreso', 'monto' => '50,00', 'motivo' => 'compra de hielo']);

        $this->assertRedirige('/caja', $r);
        $m = DB::fila('SELECT * FROM movimientos_caja ORDER BY id DESC');
        $this->assertSame('egreso', $m['tipo']);
        $this->assertSame('50.00', $m['monto']);
        $this->assertSame('compra de hielo', $m['motivo']);
        $this->assertSame($this->idCaja(), (int) $m['caja_id']);
        $this->assertSame('2026-10-15 12:00:00', $m['creado_en']);
        $this->assertSame((int) DB::valor("SELECT id FROM usuarios WHERE usuario = 'mariela'"), (int) $m['usuario_id']);
        $this->assertCount(1, Bitacora::ultimos('caja.egreso'));
    }

    /**
     * Escenario: Motivo obligatorio
     *   Dado registro un egreso sin motivo
     *   Cuando intento guardar
     *   Entonces el sistema lo rechaza
     */
    public function testElMotivoEsObligatorio(): void
    {
        $r = $this->post('/caja/movimientos', ['tipo' => 'egreso', 'monto' => '50,00', 'motivo' => '']);

        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString('Escribe el motivo del movimiento.', $r->cuerpo);
        $this->assertSame(0, (int) DB::valor('SELECT COUNT(*) FROM movimientos_caja'));
    }

    public function testElMontoEsObligatorio(): void
    {
        $r = $this->post('/caja/movimientos', ['tipo' => 'egreso', 'monto' => 'mucho', 'motivo' => 'compra de hielo']);

        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString('Escribe el monto', $r->cuerpo);
        $this->assertSame(0, (int) DB::valor('SELECT COUNT(*) FROM movimientos_caja'));
    }

    /**
     * Escenario: Efecto en el arqueo
     *   Dado un egreso de 50 Bs registrado
     *   Cuando calculo el efectivo esperado
     *   Entonces se resta del esperado
     */
    public function testElEgresoSeRestaDelEsperado(): void
    {
        $this->post('/caja/movimientos', ['tipo' => 'egreso', 'monto' => '50', 'motivo' => 'compra de hielo']);

        $totales = MovimientoCaja::totales($this->idCaja());
        $this->assertSame(0.0, $totales['ingresos']);
        $this->assertSame(50.0, $totales['egresos']);
        $this->assertSame(-50.0, $totales['neto']);
    }

    public function testRegistrarIngreso(): void
    {
        $this->post('/caja/movimientos', ['tipo' => 'ingreso', 'monto' => '120', 'motivo' => 'cambio de turno']);

        $totales = MovimientoCaja::totales($this->idCaja());
        $this->assertSame(120.0, $totales['ingresos']);
        $this->assertSame(120.0, $totales['neto']);
        $this->assertCount(1, Bitacora::ultimos('caja.ingreso'));
    }

    private function egreso(string $monto)
    {
        return $this->post('/caja/movimientos', ['tipo' => 'egreso', 'monto' => $monto, 'motivo' => 'retiro de prueba']);
    }

    /**
     * Escenario: Un egreso no supera el efectivo de la caja
     *   Dado una caja con Bs 300 de fondo y sin ventas
     *   Cuando registro un egreso de Bs 300, y después otro de Bs 0,01
     *   Entonces el primero se guarda y el segundo se rechaza sin decirle al cajero cuánto hay
     */
    public function testUnEgresoNoSuperaElEfectivoDeLaCaja(): void
    {
        $this->assertRedirige('/caja', $this->egreso('300'));
        $r = $this->egreso('0,01');

        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString('El egreso supera el efectivo que debería haber en caja. Revisa el monto.', $r->cuerpo);
        $this->assertStringNotContainsString('debería haber en caja: Bs', $r->cuerpo);
        $this->assertSame(1, (int) DB::valor('SELECT COUNT(*) FROM movimientos_caja'));
    }

    /**
     * Escenario: Las ventas en efectivo suman al disponible; los QR pendientes, no
     *   Dado Bs 300 de fondo, una venta en efectivo de Bs 100 y una por QR pendiente de Bs 500
     *   Cuando registro un egreso de Bs 400
     *   Entonces se guarda; uno de Bs 400,01 se rechaza
     */
    public function testElDisponibleCuentaVentasEnEfectivoYNoQrPendientes(): void
    {
        $mariela = (int) DB::valor("SELECT id FROM usuarios WHERE usuario = 'mariela'");
        $this->ventaDirecta($this->idCaja(), $mariela, 'efectivo', 'confirmado', 100.00);
        $this->ventaDirecta($this->idCaja(), $mariela, 'qr', 'pendiente', 500.00);

        $this->assertSame(422, $this->egreso('400,01')->estado);
        $this->assertRedirige('/caja', $this->egreso('400'));
    }

    public function testAlAdministradorSeLeDiceCuantoHay(): void
    {
        $this->entrarComo('admin');

        $r = $this->egreso('350');

        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString('El egreso supera el efectivo que debería haber en caja: Bs 300,00.', $r->cuerpo);
    }

    public function testElTopeNoAfectaALosIngresos(): void
    {
        $this->assertRedirige('/caja', $this->post('/caja/movimientos', ['tipo' => 'ingreso', 'monto' => '5000', 'motivo' => 'cambio del banco']));
        $this->assertRedirige('/caja', $this->egreso('5300'));
    }

    public function testNoHayMovimientosSinCajaAbierta(): void
    {
        DB::consulta("UPDATE cajas SET estado = 'cerrada'");

        $r = $this->post('/caja/movimientos', ['tipo' => 'egreso', 'monto' => '50', 'motivo' => 'compra de hielo']);

        $this->assertRedirige('/caja', $r);
        $this->assertSame(0, (int) DB::valor('SELECT COUNT(*) FROM movimientos_caja'));
    }

    public function testElMeseroNoRegistraMovimientos(): void
    {
        $this->entrarComo('luis');

        $this->assertSame(403, $this->post('/caja/movimientos', ['tipo' => 'egreso', 'monto' => '50', 'motivo' => 'compra de hielo'])->estado);
    }
}
