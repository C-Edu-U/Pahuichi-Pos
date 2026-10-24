<?php

declare(strict_types=1);

namespace Tests\Integracion;

use App\Core\DB;
use App\Core\Reloj;
use App\Models\Arqueo;
use App\Models\Bitacora;
use App\Models\Caja;
use App\Services\ReporteCierre;
use Tests\CasoPrueba;

/**
 * HU-27: el administrador reabre una caja cerrada con un motivo. El arqueo anterior no
 * se borra: queda reemplazado y aparece en el reporte de cierre.
 */
final class ReabrirCajaTest extends CasoPrueba
{
    private int $cajaId;
    private int $mariela;

    protected function setUp(): void
    {
        parent::setUp();
        Reloj::fijar('2026-10-15 12:00:00');
        $this->entrarComo('mariela', 'pin');
        $this->mariela = (int) DB::valor("SELECT id FROM usuarios WHERE usuario = 'mariela'");
        $this->post('/caja/abrir', ['turno_id' => (int) DB::valor("SELECT id FROM turnos WHERE clave = 'mediodia'"), 'fondo' => '200']);
        $this->cajaId = (int) DB::valor("SELECT id FROM cajas WHERE estado = 'abierta'");
        $this->ventaDirecta($this->cajaId, $this->mariela, 'efectivo', 'confirmado', 100.00);
        $this->post('/caja/conteo', ['conteo' => ['200' => '1']]); // 200: faltan 100
        $this->assertRedirige('/caja/arqueo', $this->post('/caja/arqueo', ['observacion' => 'No sé qué pasó']));
        Reloj::fijar('2026-10-15 15:30:00');
        $this->entrarComo('admin');
    }

    /**
     * Escenario: Reabrir con motivo
     *   Dado una caja cerrada con un faltante de Bs 100
     *   Cuando el administrador la reabre con el motivo «Se contó mal un billete de 100»
     *   Entonces la caja vuelve a estar abierta, el arqueo anterior se conserva con el motivo
     *   y queda en la bitácora
     */
    public function testElAdministradorReabreConMotivo(): void
    {
        $pantalla = $this->get('/caja/reabrir/' . $this->cajaId);
        $this->assertSame(200, $pantalla->estado);
        $this->assertStringContainsString('Faltante de Bs 100,00', $pantalla->cuerpo);

        $r = $this->post('/caja/reabrir/' . $this->cajaId, ['motivo' => 'Se contó mal un billete de 100']);

        $this->assertRedirige('/caja', $r);
        $caja = Caja::buscar($this->cajaId);
        $this->assertSame('abierta', $caja['estado']);
        $this->assertNull($caja['cerrada_en']);
        $this->assertNull(Arqueo::porCaja($this->cajaId));

        $anteriores = Arqueo::reemplazados($this->cajaId);
        $this->assertCount(1, $anteriores);
        $this->assertSame(-100.00, $anteriores[0]['diferencia']);
        $this->assertSame('No sé qué pasó', $anteriores[0]['observacion']);
        $this->assertSame('Se contó mal un billete de 100', $anteriores[0]['motivo_reapertura']);
        $this->assertSame('2026-10-15 15:30:00', $anteriores[0]['reemplazado_en']);
        $this->assertSame('Ana Lucía Vaca Saucedo', $anteriores[0]['reabierta_por_nombre']);

        $detalle = (string) Bitacora::ultimos('caja.reabrir')[0]['detalle'];
        $this->assertStringContainsString('"motivo":"Se contó mal un billete de 100"', $detalle);
        $this->assertStringContainsString('"diferencia":"-100.00"', $detalle);
    }

    /**
     * Escenario: Sin motivo no se reabre
     *   Dado una caja cerrada
     *   Cuando el administrador intenta reabrirla sin escribir el motivo
     *   Entonces el sistema le pide el motivo y la caja sigue cerrada
     */
    public function testSinMotivoNoSeReabre(): void
    {
        $r = $this->post('/caja/reabrir/' . $this->cajaId, ['motivo' => '   ']);

        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString('Escribe el motivo para reabrir la caja.', $r->cuerpo);
        $this->assertSame('cerrada', DB::valor('SELECT estado FROM cajas WHERE id = ?', [$this->cajaId]));
        $this->assertNotNull(Arqueo::porCaja($this->cajaId));
        $this->assertCount(0, Bitacora::ultimos('caja.reabrir'));
    }

    public function testUnMotivoDemasiadoLargoSeRechaza(): void
    {
        $r = $this->post('/caja/reabrir/' . $this->cajaId, ['motivo' => str_repeat('x', 256)]);

        $this->assertSame(422, $r->estado);
        $this->assertSame('cerrada', DB::valor('SELECT estado FROM cajas WHERE id = ?', [$this->cajaId]));
    }

    /**
     * Escenario: Cerrar otra vez
     *   Dado la caja se reabrió
     *   Cuando el cajero vuelve a contar y cierra
     *   Entonces el arqueo nuevo es el vigente y el reporte muestra el anterior con su motivo
     */
    public function testElNuevoCierreConservaElAnteriorEnElReporte(): void
    {
        $this->post('/caja/reabrir/' . $this->cajaId, ['motivo' => 'Se contó mal un billete de 100']);

        $this->entrarComo('mariela', 'pin');
        $this->post('/caja/conteo', ['conteo' => ['200' => '1', '100' => '1']]); // 300: cuadra
        $this->assertRedirige('/caja/arqueo', $this->post('/caja/arqueo', ['observacion' => '']));

        $vigente = Arqueo::porCaja($this->cajaId);
        $this->assertSame(0.00, $vigente['diferencia']);
        $this->assertSame(2, (int) DB::valor('SELECT COUNT(*) FROM arqueos WHERE caja_id = ?', [$this->cajaId]));

        $html = ReporteCierre::html(ReporteCierre::datos((array) Caja::buscar($this->cajaId), $vigente));
        $this->assertStringContainsString('La caja cuadra', $html);
        $this->assertStringContainsString('Arqueos anteriores (caja reabierta)', $html);
        $this->assertStringContainsString('Se contó mal un billete de 100', $html);
        $this->assertStringContainsString('-100,00', $html);
        $this->assertSame(200, $this->get('/caja/reporte/' . $this->cajaId)->estado);
    }

    public function testSePuedeReabrirMasDeUnaVez(): void
    {
        foreach (['Primer error', 'Segundo error'] as $motivo) {
            $this->entrarComo('admin');
            $this->assertRedirige('/caja', $this->post('/caja/reabrir/' . $this->cajaId, ['motivo' => $motivo]));
            $this->entrarComo('mariela', 'pin');
            $this->post('/caja/conteo', ['conteo' => ['200' => '1', '100' => '1']]);
            $this->post('/caja/arqueo', ['observacion' => '']);
        }

        $this->assertSame(['Primer error', 'Segundo error'], array_column(Arqueo::reemplazados($this->cajaId), 'motivo_reapertura'));
        $this->assertNotNull(Arqueo::porCaja($this->cajaId));
    }

    public function testElCajeroNoReabre(): void
    {
        $this->entrarComo('mariela', 'pin');

        $this->assertSame(403, $this->get('/caja/reabrir/' . $this->cajaId)->estado);
        $this->assertSame(403, $this->post('/caja/reabrir/' . $this->cajaId, ['motivo' => 'Quiero corregir'])->estado);
        $this->assertSame('cerrada', DB::valor('SELECT estado FROM cajas WHERE id = ?', [$this->cajaId]));
    }

    public function testConOtraCajaAbiertaNoSeReabre(): void
    {
        Reloj::fijar('2026-10-15 18:05:00');
        $this->entrarComo('jorge', 'pin');
        $this->post('/caja/abrir', ['turno_id' => (int) DB::valor("SELECT id FROM turnos WHERE clave = 'noche'"), 'fondo' => '100']);

        $this->entrarComo('admin');
        $this->assertRedirige('/caja', $this->get('/caja/reabrir/' . $this->cajaId));
        $this->assertRedirige('/caja', $this->post('/caja/reabrir/' . $this->cajaId, ['motivo' => 'Corregir']));
        $this->assertSame('cerrada', DB::valor('SELECT estado FROM cajas WHERE id = ?', [$this->cajaId]));
    }

    public function testElHistorialLlevaALaPantallaDeReapertura(): void
    {
        $html = $this->get('/caja')->cuerpo;

        $this->assertStringContainsString('href="/caja/reabrir/' . $this->cajaId . '"', $html);
        $this->assertStringNotContainsString('Se borra su conteo', $html);
    }
}
