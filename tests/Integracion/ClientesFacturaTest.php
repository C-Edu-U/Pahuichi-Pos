<?php

declare(strict_types=1);
namespace Tests\Integracion;

use App\Core\DB;
use App\Core\Reloj;
use App\Models\Cliente;
use App\Models\Factura;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\CasoPrueba;

final class ClientesFacturaTest extends CasoPrueba
{
    private array $pedido;

    protected function setUp(): void
    {
        parent::setUp();
        Reloj::fijar('2026-10-22 12:00:00');
        $this->entrarComo('mariela', 'pin');
        $this->post('/caja/abrir', ['turno_id' => (int) DB::valor("SELECT id FROM turnos WHERE clave='mediodia'"), 'fondo' => '100']);
        $p = DB::fila("SELECT * FROM productos WHERE codigo='PR-01'");
        $this->pedido = ['uuid' => '12345678-1234-4123-a123-' . bin2hex(random_bytes(6)),
            'caja_id' => (string) DB::valor("SELECT id FROM cajas WHERE estado='abierta'"), 'tipo' => 'llevar', 'mesa' => '',
            'exacto' => '1', 'total_esperado' => (string) $p['precio'],
            'lineas' => json_encode([['id' => (int) $p['id'], 'cantidad' => 1, 'nota' => '']])];
    }

    private function registrar(array $datos = []): \App\Core\Respuesta
    {
        return $this->post('/clientes', $datos + ['tipo' => 'NIT', 'doc' => '1234567', 'nombre' => 'Cliente de prueba']);
    }

    private function cobrar(array $datos = []): array
    {
        $r = $this->post('/pos/cobrar', $datos + $this->pedido);
        $this->assertSame(200, $r->estado, $r->cuerpo);
        $f = Factura::buscar((int) json_decode($r->cuerpo, true)['id']);
        $this->assertNotNull($f);
        return $f;
    }

    public function testBuscaDocumentoRegistradoYFacturaUsaNombreDelCatalogo(): void
    {
        $this->assertRedirige('/clientes', $this->registrar());
        $r = $this->get('/clientes/buscar', ['q' => '1234567']);
        $this->assertSame(200, $r->estado);
        $clientes = json_decode($r->cuerpo, true)['clientes'];
        $this->assertSame('Cliente de prueba', $clientes[0]['nombre']);
        $this->assertSame('1234567', $clientes[0]['doc']);
        $f = $this->cobrar(['cliente_doc' => '1234567', 'cliente_nombre' => 'Nombre manipulado', 'cliente_tipo' => 'NIT']);
        $this->assertSame('Cliente de prueba', $f['snapshot']['cliente']['nombre']);
        $this->assertSame('1234567', $f['snapshot']['cliente']['doc']);
        $this->assertStringContainsString('Cliente de prueba', $this->get('/facturas/' . $f['id'])->cuerpo);
    }

    public function testSinDatosConservaSnNitCeroYNoCreaCliente(): void
    {
        $f = $this->cobrar();
        $this->assertSame('S/N', $f['snapshot']['cliente']['nombre']);
        $this->assertSame('0', $f['snapshot']['cliente']['doc']);
        $this->assertSame(0, (int) DB::valor('SELECT COUNT(*) FROM clientes'));
        $otra = $this->cobrar(['cliente_doc' => '0', 'cliente_nombre' => '', 'cliente_tipo' => 'NIT']);
        $this->assertSame($f['id'], $otra['id']);
        $this->assertSame(1, (int) DB::valor('SELECT COUNT(*) FROM ventas'));
    }

    public function testClienteNuevoSeGuardaAlCobrarYSeEncuentraDespues(): void
    {
        $f = $this->cobrar(['cliente_doc' => '7654321', 'cliente_nombre' => '  Jefferson   Poeta  ', 'cliente_tipo' => 'CI']);
        $c = Cliente::porDocumento('7654321');
        $this->assertSame('Jefferson Poeta', $c['nombre']);
        $this->assertSame('CI', $c['tipo']);
        $this->assertSame($c['id'], $f['snapshot']['cliente']['id']);
        $this->assertStringContainsString('Jefferson Poeta', $this->get('/clientes')->cuerpo);
        $this->assertStringContainsString('Jefferson Poeta', $this->get('/clientes/buscar', ['q' => '7654321'])->cuerpo);
        $this->assertSame(1, (int) DB::valor("SELECT COUNT(*) FROM bitacora WHERE accion='cliente.alta'"));
    }

    public function testReintentoNoDuplicaClienteVentaNiFacturaYCambioDeClienteSeRechaza(): void
    {
        $d = ['cliente_doc' => '7654321', 'cliente_nombre' => 'Jefferson Poeta', 'cliente_tipo' => 'CI'];
        $f = $this->cobrar($d);
        $otra = $this->cobrar($d);
        $this->assertSame($f['id'], $otra['id']);
        foreach (['clientes', 'ventas', 'facturas'] as $tabla) $this->assertSame(1, (int) DB::valor('SELECT COUNT(*) FROM ' . $tabla));
        $d['cliente_doc'] = '9999999';
        $this->assertSame(422, $this->post('/pos/cobrar', $d + $this->pedido)->estado);
        $this->assertNull(Cliente::porDocumento('9999999'));
    }

    public function testNoRegistraClienteSiElCobroFalla(): void
    {
        $d = ['cliente_doc' => '7654321', 'cliente_nombre' => 'Cliente nuevo', 'cliente_tipo' => 'NIT', 'exacto' => '0', 'recibido' => '0'];
        $this->assertSame(422, $this->post('/pos/cobrar', $d + $this->pedido)->estado);
        $this->assertSame(0, (int) DB::valor('SELECT COUNT(*) FROM clientes'));
        $this->assertSame(0, (int) DB::valor('SELECT COUNT(*) FROM facturas'));
    }

    public function testDocumentoUnicoNoSobrescribeClienteNiDuplicanAltas(): void
    {
        $this->registrar();
        $this->registrar(['nombre' => 'Otro nombre']);
        $this->assertSame(1, (int) DB::valor('SELECT COUNT(*) FROM clientes'));
        $this->assertSame('Cliente de prueba', Cliente::porDocumento('1234567')['nombre']);
        $this->assertSame(1, (int) DB::valor("SELECT COUNT(*) FROM bitacora WHERE accion='cliente.alta'"));
    }

    public function testSnapshotConservaNombreAunqueCambieCatalogoYSePuedeDescargarPdf(): void
    {
        $f = $this->cobrar(['cliente_doc' => '1234567', 'cliente_nombre' => 'Nombre original', 'cliente_tipo' => 'NIT']);
        DB::consulta("UPDATE clientes SET nombre='Nombre posterior' WHERE documento='1234567'");
        $this->assertSame('Nombre original', Factura::buscar($f['id'])['snapshot']['cliente']['nombre']);
        $r = $this->get('/facturas/' . $f['id'] . '/pdf', ['solo' => 'factura', 'descargar' => '1']);
        $this->assertSame(200, $r->estado);
        $this->assertSame('application/pdf', $r->cabeceras['Content-Type']);
        $this->assertStringStartsWith('%PDF-', $r->cuerpo);
    }

    public function testCiConComplementoYBusquedaLimitada(): void
    {
        $this->registrar(['tipo' => 'CI', 'doc' => '1234567-1f']);
        $this->assertSame('CI', Cliente::porDocumento('1234567-1F')['tipo']);
        for ($i = 0; $i < 7; $i++) $this->registrar(['doc' => '999999' . $i]);
        $this->assertCount(5, json_decode($this->get('/clientes/buscar', ['q' => '999999'])->cuerpo, true)['clientes']);
        $this->assertSame([], json_decode($this->get('/clientes/buscar', ['q' => '%'])->cuerpo, true)['clientes']);
    }

    public static function invalidos(): array
    {
        return [[['doc' => 'abc']], [['doc' => '0']], [['doc' => ['123']]], [['nombre' => 'A']], [['nombre' => str_repeat('A',121)]], [['tipo' => 'OTRO']]];
    }

    #[DataProvider('invalidos')]
    public function testValidaAltaSinGuardarDatosInvalidos(array $datos): void
    {
        $this->assertSame(422, $this->registrar($datos)->estado);
        $this->assertSame(0, (int) DB::valor('SELECT COUNT(*) FROM clientes'));
    }

    public function testClienteNuevoSinNombreImpideCobrar(): void
    {
        $this->assertSame(422, $this->post('/pos/cobrar', ['cliente_doc' => '1234567', 'cliente_tipo' => 'NIT'] + $this->pedido)->estado);
        $this->assertSame(0, (int) DB::valor('SELECT COUNT(*) FROM ventas'));
    }

    public function testPermisosYCsrfDelCatalogo(): void
    {
        $this->assertSame(200, $this->get('/clientes')->estado);
        $this->assertSame(403, $this->registrar(['_csrf' => 'incorrecto'])->estado);
        $this->entrarComo('admin');
        $this->assertSame(200, $this->get('/clientes')->estado);
        $this->entrarComo('luis');
        $this->assertSame(403, $this->get('/clientes')->estado);
        $this->assertSame(403, $this->get('/clientes/buscar', ['q'=>'1234567'])->estado);
        $this->assertSame(403, $this->registrar()->estado);
    }
}
