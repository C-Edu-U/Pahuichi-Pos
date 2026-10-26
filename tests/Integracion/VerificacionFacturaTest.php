<?php

declare(strict_types=1);
namespace Tests\Integracion;

use App\Core\{Auth,DB,Reloj};
use App\Models\{Caja,Factura};
use Tests\CasoPrueba;

final class VerificacionFacturaTest extends CasoPrueba
{
    private array $f;
    private array $query;

    protected function setUp(): void
    {
        parent::setUp();
        Reloj::fijar('2026-10-22 12:00:00');
        $this->entrarComo('mariela', 'pin');
        $this->post('/caja/abrir', ['turno_id' => (int) DB::valor("SELECT id FROM turnos WHERE clave='mediodia'"), 'fondo' => '200']);
        DB::consulta('UPDATE promociones SET activo=0');
        $producto = (int) DB::valor("SELECT id FROM productos WHERE codigo='PL-01'");
        DB::consulta('UPDATE productos SET precio=38 WHERE id=?', [$producto]);
        $r = $this->post('/pos/cobrar', [
            'uuid' => '12345678-1234-4123-a123-' . bin2hex(random_bytes(6)),
            'caja_id' => (string) Caja::abierta()['id'], 'tipo' => 'llevar', 'mesa' => '',
            'total_esperado' => '38', 'exacto' => '1',
            'lineas' => json_encode([['id' => $producto, 'cantidad' => 1, 'nota' => 'Nota privada de cocina']]),
        ]);
        $this->assertSame(200, $r->estado, $r->cuerpo);
        $this->f = Factura::buscar(json_decode($r->cuerpo, true)['id']);
        $this->query = ['nit' => $this->f['snapshot']['negocio']['nit'], 'cuf' => $this->f['cuf'], 'numero' => (string) $this->f['numero'], 'monto' => $this->f['monto_total']];
        Auth::cerrar();
    }

    public function testFacturaEmitidaSeConsultaSinSesionYConLeyendaAcademica(): void
    {
        $r = $this->get('/verificar', $this->query);
        $this->assertSame(200, $r->estado);
        foreach (['Factura N° ' . $this->f['numero'], $this->f['cuf'], '22/10/2026 12:00:00', '38,00', 'EMITIDA'] as $dato) {
            $this->assertStringContainsString($dato, $r->cuerpo);
        }
        $this->assertSame(2, substr_count($r->cuerpo, 'DOCUMENTO SIN VALOR FISCAL - PROYECTO ACADÉMICO'));
        $this->assertStringNotContainsString('Nota privada de cocina', $r->cuerpo);
        $this->assertStringNotContainsString('Iniciar sesión', $r->cuerpo);
        $this->assertSame('private, no-store', $r->cabeceras['Cache-Control']);
        $this->assertSame($this->f['snapshot_json'], Factura::buscar((int) $this->f['id'])['snapshot_json']);
    }

    public function testMismoEnlaceReflejaLaAnulacionSinCambiarElSnapshot(): void
    {
        $this->assertStringContainsString('EMITIDA', $this->get('/verificar', $this->query)->cuerpo);
        DB::consulta("UPDATE facturas SET estado='anulada' WHERE id=?", [$this->f['id']]);
        DB::consulta("UPDATE ventas SET estado='anulada' WHERE id=?", [$this->f['venta_id']]);
        $r = $this->get('/verificar', $this->query);
        $this->assertSame(200, $r->estado);
        $this->assertStringContainsString('ANULADA', $r->cuerpo);
        $this->assertStringContainsString('Esta factura está anulada.', $r->cuerpo);
        $this->assertStringNotContainsString('EMITIDA', $r->cuerpo);
        $this->assertSame('private, no-store', $r->cabeceras['Cache-Control']);
        $this->assertSame($this->f['snapshot_json'], Factura::buscar((int) $this->f['id'])['snapshot_json']);
    }

    public function testCufInexistenteODatosQueNoCoincidenNoRevelanLaFactura(): void
    {
        $cuf = ($this->query['cuf'][0] === 'A' ? 'B' : 'A') . substr($this->query['cuf'], 1);
        foreach ([['cuf' => $cuf], ['nit' => '9999999999999'], ['monto' => '38.01'], ['numero' => '999999999999999999']] as $cambio) {
            $r = $this->get('/verificar', array_replace($this->query, $cambio));
            $this->assertSame(404, $r->estado);
            $this->assertStringContainsString('<h1>Factura no encontrada</h1>', $r->cuerpo);
            $this->assertStringNotContainsString($this->f['cuf'], $r->cuerpo);
            $this->assertStringNotContainsString('id="verificacion-estado"', $r->cuerpo);
        }
    }

    public function testParametrosIncompletosOInvalidosMuestranElMismoMensajeSinEjecutarHtml(): void
    {
        foreach ([[], ['cuf' => ['A']], ['cuf' => '<script>alert(1)</script>'], ['nit' => 'x'], ['monto' => '-1.00']] as $cambio) {
            $r = $this->get('/verificar', $cambio === [] ? [] : array_replace($this->query, $cambio));
            $this->assertSame(404, $r->estado);
            $this->assertStringContainsString('<h1>Factura no encontrada</h1>', $r->cuerpo);
            $this->assertStringNotContainsString('<script>alert(1)</script>', $r->cuerpo);
        }
    }
}
