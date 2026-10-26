<?php

declare(strict_types=1);

namespace Tests\Integracion;

use App\Core\DB;
use App\Core\Reloj;
use App\Core\Vista;
use App\Models\Bitacora;
use App\Models\ConfiguracionTickets;
use App\Models\Factura;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\CasoPrueba;

/** HU-56: escenarios de marca y bitácora por la ruta real, más permisos y conservación de la venta. */
final class ReimpresionTicketsTest extends CasoPrueba
{
    private int $factura;
    private int $cajero;

    protected function setUp(): void
    {
        parent::setUp();
        Reloj::fijar('2026-10-21 12:00:00');
        $this->cajero = (int) $this->entrarComo('mariela', 'pin')['id'];
        $this->assertRedirige('/pos', $this->post('/caja/abrir', [
            'turno_id' => (int) DB::valor("SELECT id FROM turnos WHERE clave='mediodia'"), 'fondo' => '100',
        ]));
        $p = DB::fila("SELECT * FROM productos WHERE codigo='PR-01'");
        $r = $this->post('/pos/cobrar', [
            'uuid' => '12345678-1234-4123-a123-' . bin2hex(random_bytes(6)),
            'caja_id' => (string) DB::valor("SELECT id FROM cajas WHERE estado='abierta'"),
            'tipo' => 'mesa', 'mesa' => '5', 'exacto' => '1', 'total_esperado' => (string) $p['precio'],
            'lineas' => json_encode([['id' => (int) $p['id'], 'cantidad' => 1, 'nota' => 'Sin ají']]),
        ]);
        $this->assertSame(200, $r->estado, $r->cuerpo);
        $this->factura = (int) json_decode($r->cuerpo, true)['id'];
    }

    private function ruta(): string
    {
        return '/facturas/' . $this->factura . '/reimpresion';
    }

    public static function tickets(): array
    {
        return [['copia', 'caja'], ['comanda', 'cocina'], ['factura', 'caja']];
    }

    #[DataProvider('tickets')]
    public function testGeneraCadaTicketMarcadoYRegistraQuienCuandoYCual(string $tipo, string $destino): void
    {
        $original = Factura::buscar($this->factura);
        $contador = DB::filas('SELECT * FROM correlativos');
        $r = $this->post($this->ruta(), ['tipo' => $tipo]);
        $this->assertSame(200, $r->estado);
        $this->assertSame('application/pdf', $r->cabeceras['Content-Type']);
        $this->assertStringStartsWith('%PDF-', $r->cuerpo);
        $this->assertStringContainsString('inline;', $r->cabeceras['Content-Disposition']);
        $this->assertStringContainsString('Reimpresion-' . $tipo, $r->cabeceras['Content-Disposition']);
        $this->assertSame((string) strlen($r->cuerpo), $r->cabeceras['Content-Length']);
        $this->assertSame(1, preg_match_all('~/Type /Page\b~', $r->cuerpo));

        $html = Vista::render('facturas/pdf', [
            'perfil' => ConfiguracionTickets::todas()[$destino], 'tipos' => [$tipo],
            'reimpresion' => true, 's' => $original['snapshot'],
        ], null);
        $this->assertStringContainsString('<p class="ticket__reimpresion">REIMPRESIÓN</p>', $html);
        $this->assertSame(1, substr_count($html, '<article '));
        $b = Bitacora::ultimos('factura.reimpresion');
        $this->assertCount(1, $b);
        $this->assertSame($this->cajero, (int) $b[0]['usuario_id']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(?:\.\d{1,6})?$/D', $b[0]['creado_en']);
        $this->assertSame('facturas', $b[0]['entidad']);
        $this->assertSame($this->factura, (int) $b[0]['entidad_id']);
        $d = json_decode($b[0]['detalle'], true);
        $this->assertSame($tipo, $d['tipo']);
        $this->assertSame(ConfiguracionTickets::TIPOS[$tipo], $d['ticket']);
        $this->assertSame($destino, $d['destino']);
        $this->assertSame((int) $original['numero'], $d['factura_numero']);
        $this->assertSame('generacion', $d['accion']);
        $this->assertSame($original, Factura::buscar($this->factura));
        $this->assertSame($contador, DB::filas('SELECT * FROM correlativos'));
        $this->assertSame(1, (int) DB::valor('SELECT COUNT(*) FROM ventas'));
        $this->assertSame(1, (int) DB::valor('SELECT COUNT(*) FROM facturas'));
        $this->assertSame(1, (int) DB::valor('SELECT COUNT(*) FROM pagos'));
    }

    public function testEntradaSeRegistraUnaVezYSeRepiteSoloAlVolverDesdeVerTickets(): void
    {
        $url = '/facturas/' . $this->factura;
        $this->get($url);
        $this->assertCount(0, Bitacora::ultimos('factura.entrada_reimpresion'));
        for ($i = 0; $i < 10; $i++) {
            $this->assertSame(200, $this->get($url, ['reimpresion' => '1'])->estado);
        }
        $entradas = Bitacora::ultimos('factura.entrada_reimpresion');
        $this->assertCount(1, $entradas);
        $this->assertSame($this->cajero, (int) $entradas[0]['usuario_id']);
        $this->assertSame($this->factura, (int) $entradas[0]['entidad_id']);
        $this->assertSame('entrada', json_decode($entradas[0]['detalle'], true)['accion']);
        $this->assertCount(0, Bitacora::ultimos('factura.reimpresion'));
        $this->get($url);
        $this->get($url);
        $this->assertCount(1, Bitacora::ultimos('factura.entrada_reimpresion'));
        $this->get($url, ['reimpresion' => '1']);
        $this->assertCount(2, Bitacora::ultimos('factura.entrada_reimpresion'));
        $this->get($url . '/pdf', ['seleccion' => 'copia', 'reimpresion' => '1', 'descargar' => '1']);
        $this->assertCount(1, Bitacora::ultimos('factura.reimpresion'));
        $this->get($url, ['reimpresion' => '1']);
        $this->assertCount(2, Bitacora::ultimos('factura.entrada_reimpresion'));
        $admin = $this->entrarComo('admin');
        $this->get($url, ['reimpresion' => '1']);
        $this->get($url, ['reimpresion' => '1']);
        $entradas = Bitacora::ultimos('factura.entrada_reimpresion');
        $this->assertCount(3, $entradas);
        $this->assertSame((int) $admin['id'], (int) $entradas[0]['usuario_id']);
        $html = $this->get('/bitacora', ['accion' => 'factura.entrada_reimpresion'])->cuerpo;
        $this->assertStringContainsString('Entró a Reimpresión', $html);
    }

    public function testEntrarCambiarDeModoYRecargarNoRegistraPeroPedirPdfSi(): void
    {
        $url = '/facturas/' . $this->factura;
        // Reproduce los clics repetidos que antes añadían tres filas por petición.
        foreach (['mariela', 'admin'] as $usuario) {
            $this->entrarComo($usuario, $usuario === 'admin' ? 'clave' : 'pin');
            for ($i = 0; $i < 10; $i++) {
                $this->assertSame(200, $this->get($url, ['reimpresion' => '1'])->estado);
                $this->assertSame(200, $this->get($url)->estado);
            }
        }
        $this->assertCount(0, Bitacora::ultimos('factura.reimpresion'));
        $r = $this->get($url . '/pdf', ['reimpresion' => '1', 'seleccion' => 'copia']);
        $this->assertSame(200, $r->estado);
        $this->assertCount(1, Bitacora::ultimos('factura.reimpresion'));
        $this->assertSame('ver', json_decode(Bitacora::ultimos('factura.reimpresion')[0]['detalle'], true)['operacion']);
        for ($i = 0; $i < 10; $i++) $this->get($url, ['reimpresion' => '1']);
        $this->assertCount(1, Bitacora::ultimos('factura.reimpresion'));
        $r = $this->get($url . '/pdf', ['reimpresion' => '1', 'seleccion' => 'caja', 'descargar' => '1']);
        $this->assertSame(200, $r->estado);
        $registros = Bitacora::ultimos('factura.reimpresion');
        $this->assertCount(3, $registros);
        foreach (array_slice($registros, 0, 2) as $fila) {
            $detalle = json_decode($fila['detalle'], true);
            $this->assertSame('descargar', $detalle['operacion']);
            $this->assertContains($detalle['tipo'], ['copia', 'factura']);
        }
    }

    public function testDescargaRepetidaConservaSnapshotAunqueCambieElCatalogo(): void
    {
        $antes = Factura::buscar($this->factura);
        DB::consulta("UPDATE productos SET precio=99, nombre='Nombre nuevo' WHERE codigo='PR-01'");
        for ($i = 0; $i < 2; $i++) {
            $r = $this->post($this->ruta(), ['tipo' => 'factura', 'descargar' => '1']);
            $this->assertSame(200, $r->estado);
            $this->assertStringContainsString('attachment;', $r->cabeceras['Content-Disposition']);
        }
        $this->assertSame($antes, Factura::buscar($this->factura));
        $this->assertCount(2, Bitacora::ultimos('factura.reimpresion'));
    }

    public function testUsaPerfilVigenteSinDependerDeLosTicketsDelLoteHabitual(): void
    {
        DB::consulta("UPDATE configuracion_tickets SET ancho=58, columnas=32, copias=2, copia=0, factura=1 WHERE destino='caja'");
        $r = $this->post($this->ruta(), ['tipo' => 'copia']);
        $this->assertSame(200, $r->estado);
        $this->assertSame(2, preg_match_all('~/Type /Page\b~', $r->cuerpo));
        $this->assertMatchesRegularExpression('~/MediaBox\s*\[0\.000 0\.000 164\.409~', $r->cuerpo);
        $this->assertSame(0, ConfiguracionTickets::todas()['caja']['copia']);
    }

    public static function tiposInvalidos(): array
    {
        return [['todos'], ['../factura'], [['copia']], ['']];
    }

    #[DataProvider('tiposInvalidos')]
    public function testRechazaTipoInvalidoSinRegistrarReimpresion(mixed $tipo): void
    {
        $this->assertSame(422, $this->post($this->ruta(), ['tipo' => $tipo])->estado);
        $this->assertCount(0, Bitacora::ultimos('factura.reimpresion'));
    }

    public function testExigeCsrfYElHistorialSoloOfreceReimpresiones(): void
    {
        $this->assertSame(403, $this->post($this->ruta(), ['tipo' => 'copia', '_csrf' => 'incorrecto'])->estado);
        $this->assertSame(405, $this->get($this->ruta())->estado);
        $html = $this->get('/facturas/' . $this->factura)->cuerpo;
        $this->assertStringContainsString('>Reimpresión</a>', $html);
        $this->assertStringContainsString('name="_csrf"', $html);
        $this->assertStringContainsString('<p class="ticket__reimpresion" hidden>REIMPRESIÓN</p>', $html);
        $this->assertStringContainsString('PDF de tickets', $html);
        $this->assertCount(0, Bitacora::ultimos('factura.reimpresion'));
    }

    public function testOtroCajeroYMeseroNoPuedenReimprimirLaVenta(): void
    {
        $this->entrarComo('jorge', 'pin');
        $this->assertSame(403, $this->post($this->ruta(), ['tipo' => 'copia'])->estado);
        $this->entrarComo('luis');
        $this->assertSame(403, $this->post($this->ruta(), ['tipo' => 'copia'])->estado);
        $this->assertCount(0, Bitacora::ultimos('factura.reimpresion'));
    }

    public function testAdministradorPuedeReimprimirYSeRegistraComoAutor(): void
    {
        $admin = $this->entrarComo('admin');
        $this->assertSame(200, $this->post($this->ruta(), ['tipo' => 'copia'])->estado);
        $this->assertSame((int) $admin['id'], (int) Bitacora::ultimos('factura.reimpresion')[0]['usuario_id']);
    }

    public function testRechazaVentaAnuladaSinRegistrarReimpresion(): void
    {
        DB::consulta("UPDATE ventas SET estado='anulada' WHERE id=?", [Factura::buscar($this->factura)['venta_id']]);
        $this->assertSame(422, $this->post($this->ruta(), ['tipo' => 'copia'])->estado);
        $this->assertCount(0, Bitacora::ultimos('factura.reimpresion'));
    }
}
