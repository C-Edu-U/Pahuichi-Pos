<?php

declare(strict_types=1);
namespace Tests\Integracion;

use App\Core\DB;
use App\Core\Reloj;
use App\Models\ConfiguracionTickets;
use App\Models\Factura;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\CasoPrueba;
use App\Services\TicketsPdf;

final class TicketsPdfTest extends CasoPrueba
{
    private int $factura;
    private string $snapshot;

    protected function setUp(): void
    {
        parent::setUp();
        Reloj::fijar('2026-10-21 12:00:00');
        $this->entrarComo('mariela', 'pin');
        $this->post('/caja/abrir', ['turno_id' => (int) DB::valor("SELECT id FROM turnos WHERE clave='mediodia'"), 'fondo' => '100']);
        $p = DB::fila("SELECT * FROM productos WHERE codigo='PR-01'");
        $r = $this->post('/pos/cobrar', [
            'uuid' => '12345678-1234-4123-a123-' . bin2hex(random_bytes(6)),
            'caja_id' => (string) DB::valor("SELECT id FROM cajas WHERE estado='abierta'"),
            'tipo' => 'llevar', 'mesa' => '', 'exacto' => '1', 'total_esperado' => (string) $p['precio'],
            'lineas' => json_encode([['id' => (int) $p['id'], 'cantidad' => 1, 'nota' => '']]),
        ]);
        $this->assertSame(200, $r->estado, $r->cuerpo);
        $this->factura = (int) json_decode($r->cuerpo, true)['id'];
        $this->snapshot = Factura::buscar($this->factura)['snapshot_json'];
        $this->entrarComo('admin');
    }

    private function guardar(string $destino, array $d = []): \App\Core\Respuesta
    {
        return $this->post('/config/tickets/' . $destino, $d + [
            'ancho' => '58', 'columnas' => '32', 'copias' => '2', 'factura' => '1', 'copia' => '1',
        ]);
    }

    public function testGuardaPerfilYLoMuestraAlRecargar(): void
    {
        $this->assertRedirige('/config#impresoras', $this->guardar('caja'));
        $p = ConfiguracionTickets::todas()['caja'];
        $this->assertSame([58, 32, 2], [$p['ancho'], $p['columnas'], $p['copias']]);
        $html = $this->get('/config')->cuerpo;
        $this->assertStringContainsString('id="i-caja-columnas" name="columnas" type="number" min="24" max="48" step="1" required value="32"', $html);
        $this->assertStringContainsString('value="58" selected', $html);
        $this->assertSame(1, (int) DB::valor("SELECT COUNT(*) FROM bitacora WHERE accion='config.tickets'"));
    }

    private function comprobarPdf(\App\Core\Respuesta $r): void
    {
        $this->assertSame(200, $r->estado);
        $this->assertSame('application/pdf', $r->cabeceras['Content-Type']);
        $this->assertStringStartsWith('%PDF-', $r->cuerpo);
        $this->assertStringContainsString('%%EOF', $r->cuerpo);
        $this->assertSame((string) strlen($r->cuerpo), $r->cabeceras['Content-Length']);
        $this->assertStringNotContainsString('window.print', $r->cuerpo);
        $this->assertStringNotContainsString('/JavaScript', $r->cuerpo);
        $this->assertStringNotContainsString('/OpenAction', $r->cuerpo);
    }

    public function testPdfRespetaAnchoColumnasSeleccionYCopias(): void
    {
        $this->guardar('caja');
        $salida = TicketsPdf::preparar('caja');
        $this->assertSame([58, 32, 2], [$salida['perfil']['ancho'], $salida['perfil']['columnas'], $salida['perfil']['copias']]);
        $this->assertSame(['copia', 'factura'], $salida['tipos']);
        $r = $this->get('/facturas/' . $this->factura . '/pdf', ['destino' => 'caja']);
        $this->comprobarPdf($r);
        $this->assertStringContainsString('inline;', $r->cabeceras['Content-Disposition']);
        $this->assertMatchesRegularExpression('~/MediaBox\s*\[0\.000 0\.000 164\.409~', $r->cuerpo);
        $this->assertSame(4, preg_match_all('~/Type /Page\b~', $r->cuerpo));
    }

    public function testCocinaTieneSuPropioPerfil(): void
    {
        $this->assertRedirige('/config#impresoras', $this->guardar('cocina', ['ancho' => '76', 'columnas' => '42', 'copias' => '3', 'comanda' => '1', 'factura' => '0', 'copia' => '0']));
        $this->assertSame(['comanda'], TicketsPdf::preparar('cocina')['tipos']);
        $r = $this->get('/facturas/' . $this->factura . '/pdf', ['destino' => 'cocina']);
        $this->comprobarPdf($r);
        $this->assertSame(3, preg_match_all('~/Type /Page\b~', $r->cuerpo));
        $this->assertMatchesRegularExpression('~/MediaBox\s*\[0\.000 0\.000 215\.433~', $r->cuerpo);
        $this->assertSame(76, ConfiguracionTickets::todas()['caja']['ancho']);
    }

    public function testCambioSeAplicaAlSiguientePdfSinModificarFactura(): void
    {
        $url = '/facturas/' . $this->factura . '/pdf';
        $antes = $this->get($url, ['destino' => 'caja'])->cuerpo;
        $this->guardar('caja', ['copias' => '3']);
        $despues = $this->get($url, ['destino' => 'caja'])->cuerpo;
        $this->assertSame(2, preg_match_all('~/Type /Page\b~', $antes));
        $this->assertSame(6, preg_match_all('~/Type /Page\b~', $despues));
        $this->assertSame($this->snapshot, Factura::buscar($this->factura)['snapshot_json']);
        $this->assertSame(1, (int) DB::valor('SELECT COUNT(*) FROM ventas'));
    }

    public function testFacturaIndividualUsaPerfilDeCaja(): void
    {
        $this->guardar('caja');
        $this->assertSame(['factura'], TicketsPdf::preparar('caja', true)['tipos']);
        $r = $this->get('/facturas/' . $this->factura . '/pdf', ['destino' => 'caja', 'solo' => 'factura']);
        $this->comprobarPdf($r);
        $this->assertSame(2, preg_match_all('~/Type /Page\b~', $r->cuerpo));
        $this->guardar('caja', ['factura' => '0']);
        $this->assertSame(422, $this->get('/facturas/' . $this->factura . '/pdf', ['destino' => 'caja', 'solo' => 'factura'])->estado);
    }

    public function testDescargaDirectaYVistaSinImpresion(): void
    {
        $r = $this->get('/facturas/' . $this->factura . '/pdf', ['descargar' => '1']);
        $this->comprobarPdf($r);
        $this->assertStringContainsString('attachment;', $r->cabeceras['Content-Disposition']);
        $this->assertStringContainsString('.pdf"', $r->cabeceras['Content-Disposition']);
        $html = $this->get('/facturas/' . $this->factura)->cuerpo;
        $this->assertStringContainsString('name="descargar" value="1"', $html);
        $this->assertStringNotContainsString('window.print', $html);
        $this->assertStringNotContainsString('aviso-reimpresion', $html);
    }

    public static function invalidos(): array
    {
        return [
            [['ancho' => '80']], [['ancho' => ['58']]], [['columnas' => '0']], [['columnas' => '49']],
            [['copias' => '0']], [['copias' => '4']], [['copias' => '1.5']], [['factura' => '2']],
            [['factura' => '0', 'copia' => '0', 'comanda' => '0']],
        ];
    }

    #[DataProvider('invalidos')]
    public function testRechazaConfiguracionInvalidaSinCambios(array $d): void
    {
        $antes = ConfiguracionTickets::todas();
        $this->assertSame(422, $this->guardar('caja', $d)->estado);
        $this->assertSame($antes, ConfiguracionTickets::todas());
        $this->assertSame(0, (int) DB::valor("SELECT COUNT(*) FROM bitacora WHERE accion='config.tickets'"));
    }

    public function testExigeAdministradorYCsrf(): void
    {
        $this->assertSame(403, $this->guardar('caja', ['_csrf' => 'incorrecto'])->estado);
        $this->entrarComo('mariela', 'pin');
        $this->assertSame(403, $this->guardar('caja')->estado);
        $this->assertSame(200, $this->get('/facturas/' . $this->factura . '/pdf')->estado);
        $this->entrarComo('jorge', 'pin');
        $this->assertSame(403, $this->get('/facturas/' . $this->factura . '/pdf')->estado);
    }
}
