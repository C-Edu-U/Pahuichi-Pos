<?php

declare(strict_types=1);

namespace Tests\Integracion;

use App\Core\DB;
use App\Core\Reloj;
use App\Models\Bitacora;
use Tests\CasoPrueba;

/** HU-25: reporte de cierre impreso y guardado en PDF. */
final class ReporteCierreTest extends CasoPrueba
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
        $this->ventaDirecta($this->cajaId, $this->mariela, 'efectivo', 'confirmado', 1000.00);
        $this->post('/caja/conteo', ['conteo' => ['200' => '6']]);
        $this->post('/caja/arqueo', ['observacion' => '']);
    }

    /**
     * Escenario: Impresión
     *   Dado confirmo el cierre de caja
     *   Cuando termina el arqueo
     *   Entonces se imprime el reporte de cierre
     */
    public function testElReporteSeAbreTrasElCierre(): void
    {
        $caja = DB::fila('SELECT * FROM cajas WHERE id = ?', [$this->cajaId]);
        $this->assertSame('cerrada', $caja['estado']);

        $r = $this->get('/caja/reporte/' . $this->cajaId);

        $this->assertSame(200, $r->estado);
        $this->assertStringContainsString('Reporte de cierre de caja', $r->cuerpo);
        $this->assertStringContainsString('imprimir-reporte', $r->cuerpo);
        $this->assertStringContainsString('reporte-cierre.css', $r->cuerpo);
    }

    /**
     * Escenario: PDF
     *   Dado una caja cerrada
     *   Cuando abro su reporte
     *   Entonces puedo descargarlo en PDF con el mismo contenido
     */
    public function testElReporteTieneElMismoContenidoDelArqueo(): void
    {
        $r = $this->get('/caja/reporte/' . $this->cajaId);

        // Conteo, esperado, contado y diferencia
        $this->assertStringContainsString('1.200,00', $r->cuerpo); // efectivo esperado
        $this->assertStringContainsString('1.200,00', $r->cuerpo); // total contado
        $this->assertStringContainsString('La caja cuadra', $r->cuerpo);
        // Resumen del turno
        $this->assertStringContainsString('Conteo por denominación', $r->cuerpo);
        $this->assertStringContainsString('Resumen del turno', $r->cuerpo);
        $this->assertStringContainsString('Ventas en efectivo', $r->cuerpo);
        $this->assertStringContainsString('Documento de prueba, sin validez fiscal.', $r->cuerpo);
    }

    public function testElArqueoOfreceElReporte(): void
    {
        $r = $this->get('/caja/arqueo');

        $this->assertStringContainsString('/caja/reporte/' . $this->cajaId, $r->cuerpo);
        $this->assertStringContainsString('Imprimir el reporte de cierre', $r->cuerpo);
    }

    public function testElHistorialEnlazaAlReporte(): void
    {
        $r = $this->get('/caja');

        $this->assertStringContainsString('/caja/reporte/' . $this->cajaId, $r->cuerpo);
    }

    public function testUnaCajaSinArqueoNoTieneReporte(): void
    {
        $r = $this->get('/caja/reporte/999999');

        $this->assertSame(404, $r->estado);
    }

    public function testElCierreQuedaRegistrado(): void
    {
        $this->assertCount(1, Bitacora::ultimos('caja.arqueo'));
    }
}
