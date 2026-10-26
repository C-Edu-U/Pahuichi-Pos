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

/** HU-25: reporte de cierre en PDF, con lo guardado al confirmar el arqueo. */
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
        $this->post('/caja/movimientos', ['tipo' => 'egreso', 'monto' => '15', 'motivo' => 'compra de hielo']);
        $this->post('/caja/conteo', ['conteo' => ['200' => '5', '100' => '1', '50' => '1', '20' => '1', '10' => '1']]); // 1.180
        $this->post('/caja/arqueo', ['observacion' => 'Faltan 5 del vuelto']);
    }

    private function html(): string
    {
        $caja = Caja::buscar($this->cajaId);
        $this->assertNotNull($caja);
        return ReporteCierre::html(ReporteCierre::datos($caja, (array) Arqueo::porCaja($this->cajaId)));
    }

    /**
     * Escenario: Impresión
     *   Dado confirmo el cierre de caja
     *   Cuando termina el arqueo
     *   Entonces abro el reporte de cierre en PDF para imprimirlo
     */
    public function testElReporteEsUnPdf(): void
    {
        $this->assertSame('cerrada', DB::valor('SELECT estado FROM cajas WHERE id = ?', [$this->cajaId]));

        $r = $this->get('/caja/reporte/' . $this->cajaId);

        $this->assertSame(200, $r->estado);
        $this->assertSame('application/pdf', $r->cabeceras['Content-Type']);
        $this->assertStringStartsWith('%PDF-', $r->cuerpo);
        $this->assertSame('inline; filename="Cierre-de-caja-2026-10-15-medio-dia-' . $this->cajaId . '.pdf"', $r->cabeceras['Content-Disposition']);
        $this->assertSame((string) strlen($r->cuerpo), $r->cabeceras['Content-Length']);
    }

    /**
     * Escenario: PDF
     *   Dado una caja cerrada
     *   Cuando descargo su reporte
     *   Entonces baja como archivo PDF
     */
    public function testElReporteSeDescarga(): void
    {
        $r = $this->get('/caja/reporte/' . $this->cajaId, ['descargar' => '1']);

        $this->assertStringStartsWith('attachment; filename="Cierre-de-caja-', $r->cabeceras['Content-Disposition']);
        $this->assertStringStartsWith('%PDF-', $r->cuerpo);
    }

    public function testElReporteTieneElContenidoDelArqueo(): void
    {
        $html = $this->html();

        $this->assertStringContainsString('Reporte de cierre de caja', $html);
        $this->assertStringContainsString('Mariela Suárez Añez', $html);
        $this->assertStringContainsString('<th scope="row">Efectivo esperado</th><td class="num">Bs 1.185,00</td>', $html);
        $this->assertStringContainsString('<th scope="row">Total contado</th><td class="num">Bs 1.180,00</td>', $html);
        $this->assertStringContainsString('Faltante de Bs 5,00', $html);
        $this->assertStringContainsString('Faltan 5 del vuelto', $html);
        $this->assertStringContainsString('<td>Billete de Bs 200</td><td class="num">5</td><td class="num">1.000,00</td>', $html);
        $this->assertStringContainsString('compra de hielo', $html);
        $this->assertStringContainsString('Resumen del turno', $html);
        $this->assertStringContainsString('Documento de prueba, sin validez fiscal.', $html);
        $this->assertStringContainsString('Cerrada</th><td class="num">15/10/2026 12:00</td>', $html);
    }

    /**
     * Escenario: El reporte no cambia
     *   Dado la caja se cerró con un esperado de Bs 1.185
     *   Cuando después aparece una venta más en esa caja
     *   Entonces el reporte sigue mostrando lo que se guardó al cerrar
     */
    public function testElReporteUsaLoGuardadoAlCerrar(): void
    {
        $this->ventaDirecta($this->cajaId, $this->mariela, 'efectivo', 'confirmado', 500.00);

        $html = $this->html();

        $this->assertStringContainsString('Bs 1.185,00', $html);
        $this->assertStringNotContainsString('1.685,00', $html);
    }

    public function testElArqueoOfreceElReporte(): void
    {
        $html = $this->get('/caja/arqueo')->cuerpo;

        $this->assertStringContainsString('href="/caja/reporte/' . $this->cajaId . '" target="_blank"', $html);
        $this->assertStringContainsString('href="/caja/reporte/' . $this->cajaId . '?descargar=1"', $html);
        $this->assertStringNotContainsString('window.print', $html);
    }

    public function testElHistorialEnlazaAlReporte(): void
    {
        $this->assertStringContainsString('/caja/reporte/' . $this->cajaId, $this->get('/caja')->cuerpo);
    }

    public function testUnaCajaQueNoExisteNoTieneReporte(): void
    {
        $this->assertSame(404, $this->get('/caja/reporte/999999')->estado);
    }

    public function testUnaCajaAbiertaTodaviaNoTieneReporte(): void
    {
        Reloj::fijar('2026-10-15 18:05:00');
        $this->entrarComo('jorge', 'pin');
        $this->post('/caja/abrir', ['turno_id' => (int) DB::valor("SELECT id FROM turnos WHERE clave = 'noche'"), 'fondo' => '100']);
        $noche = (int) DB::valor("SELECT id FROM cajas WHERE estado = 'abierta'");
        $this->post('/caja/conteo', ['conteo' => ['100' => '1']]);

        $this->assertRedirige('/caja', $this->get('/caja/reporte/' . $noche));
    }

    public function testElCierreQuedaRegistrado(): void
    {
        $this->assertCount(1, Bitacora::ultimos('caja.arqueo'));
    }
}
