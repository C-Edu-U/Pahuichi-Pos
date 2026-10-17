<?php

declare(strict_types=1);
namespace Tests\Integracion;

use App\Core\App;
use App\Core\Csrf;
use App\Core\DB;
use App\Core\Peticion;
use App\Models\Factura;
use App\Services\LeyendaService;
use Tests\CasoPrueba;

/** HU-10: recorrido real de venta en efectivo a factura con leyenda persistente. */
final class FacturaLeyendaTest extends CasoPrueba
{
    private int $caja;
    private int $producto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entrarComo('mariela', 'pin');
        $turno = DB::valor("SELECT id FROM turnos WHERE clave = 'mediodia'");
        $this->post('/caja/abrir', ['turno_id' => (string) $turno, 'fondo' => '300']);
        $this->caja = (int) DB::valor('SELECT id FROM cajas WHERE estado = \'abierta\'');
        $this->producto = (int) DB::valor("SELECT id FROM productos WHERE codigo = 'PL-02'");
    }

    private function datos(array $cambios = []): array
    {
        $h = bin2hex(random_bytes(16));
        return $cambios + ['uuid' => substr($h, 0, 8) . '-' . substr($h, 8, 4) . '-4' . substr($h, 13, 3) . '-8' . substr($h, 17, 3) . '-' . substr($h, 20),
            'caja_id' => (string) $this->caja, 'tipo' => 'mesa', 'mesa' => '4', 'exacto' => '1', 'recibido' => '35.00', 'total_esperado' => '35.00',
            'lineas' => json_encode([['id' => $this->producto, 'cantidad' => 1, 'nota' => 'sin cebolla']])];
    }

    private function cobrar(array $d = []): array
    {
        $r = $this->post('/pos/cobrar', $this->datos($d));
        $this->assertSame(200, $r->estado, $r->cuerpo);
        return json_decode($r->cuerpo, true, 512, JSON_THROW_ON_ERROR);
    }

    /** Escenario 2: dada una venta, al emitir e imprimir su factura lleva una leyenda activa. */
    public function testVentaPagoFacturaTicketsYPersistencia(): void
    {
        $d = $this->cobrar(['exacto' => '0', 'recibido' => '100.00']);
        $f = Factura::buscar($d['id']);
        $this->assertSame('35.00', $f['monto_total']);
        $this->assertSame('65.00', DB::valor('SELECT cambio FROM pagos WHERE venta_id = ?', [$f['venta_id']]));
        $this->assertSame('35.00', DB::valor('SELECT precio_unitario FROM detalle_venta WHERE venta_id = ?', [$f['venta_id']]));
        $this->assertSame(1, (int) DB::valor('SELECT activo FROM leyendas WHERE id = ?', [$f['leyenda_id']]));
        $this->assertMatchesRegularExpression('/^[A-F0-9]{28}$/', $f['cuf']);
        $this->assertSame('sin cebolla', $f['snapshot']['lineas'][0]['nota']);
        $r = $this->get($d['tickets']);
        $this->assertSame(200, $r->estado);
        $this->assertStringContainsString($f['leyenda'], $r->cuerpo);
        $this->assertStringContainsString('Comanda', $r->cuerpo);
        $this->assertStringContainsString('Copia de caja', $r->cuerpo);
        $this->assertSame(2, substr_count($r->cuerpo, 'DOCUMENTO SIN VALOR FISCAL - PROYECTO ACADÉMICO'));
        $this->assertStringNotContainsString('<style', $r->cuerpo);
        DB::consulta("UPDATE productos SET precio = 99 WHERE id = ?", [$this->producto]);
        DB::consulta("UPDATE negocio SET razon_social = 'Otro negocio' WHERE id = 1");
        $r2 = $this->get($d['tickets']);
        $this->assertStringContainsString('35,00', $r2->cuerpo);
        $this->assertStringContainsString('PAHUICHI S.R.L.', $r2->cuerpo);
        $this->assertSame($f['snapshot_json'], Factura::buscar($d['id'])['snapshot_json']);
        $this->assertStringContainsString($d['tickets'], $this->get('/ventas')->cuerpo);
    }

    /** Escenario 3: desactivar afecta a facturas siguientes y no modifica una ya emitida. */
    public function testDesactivarExcluyeDeFacturasSiguientes(): void
    {
        $primera = $this->cobrar();
        $f = Factura::buscar($primera['id']);
        $this->entrarComo('admin');
        LeyendaService::agregar('Leyenda nueva para las próximas facturas.');
        $this->post('/config/leyendas/' . $f['leyenda_id'] . '/estado', ['activo' => '0']);
        for ($i = 0; $i < 12; $i++) {
            $otra = Factura::buscar($this->cobrar()['id']);
            $this->assertNotSame((int) $f['leyenda_id'], (int) $otra['leyenda_id']);
            $this->assertSame(1, (int) DB::valor('SELECT activo FROM leyendas WHERE id = ?', [$otra['leyenda_id']]));
        }
        $this->assertStringContainsString($f['leyenda'], $this->get($primera['tickets'])->cuerpo);
        $this->assertSame($f['snapshot_json'], Factura::buscar($primera['id'])['snapshot_json']);
    }

    /** Fixture controlado: la elección consulta el catálogo actual y admite una leyenda recién agregada. */
    public function testNuevaLeyendaPuedeSerElegida(): void
    {
        DB::consulta('UPDATE leyendas SET activo = 0');
        $id = LeyendaService::agregar('Única activa en esta prueba controlada.');
        $f = Factura::buscar($this->cobrar()['id']);
        $this->assertSame($id, (int) $f['leyenda_id']);
        $this->assertSame('Ley N° 453: Única activa en esta prueba controlada.', $f['leyenda']);
    }

    public function testReintentoNoDuplicaVentaNiNumeroNiPago(): void
    {
        $datos = $this->datos();
        $a = $this->post('/pos/cobrar', $datos);
        $b = $this->post('/pos/cobrar', $datos);
        $this->assertSame($a->cuerpo, $b->cuerpo);
        foreach (['ventas', 'pagos', 'facturas', 'detalle_venta'] as $tabla) $this->assertSame(1, (int) DB::valor('SELECT COUNT(*) FROM ' . $tabla));
        $this->assertSame(1, (int) DB::valor('SELECT ultimo_numero FROM correlativos'));
        $this->assertSame(422, $this->post('/pos/cobrar', ['mesa' => '2'] + $datos)->estado);
        $seg = $this->cobrar();
        $this->assertSame(2, $seg['numero']);
    }

    public function testErroresNoDejanRegistrosParciales(): void
    {
        foreach ([['exacto' => '0', 'recibido' => '10.00'], ['total_esperado' => '0.01'], ['mesa' => '0'], ['uuid' => 'mal'], ['lineas' => '[]'], ['lineas' => '{'], ['lineas' => json_encode([['id' => $this->producto, 'cantidad' => -1]])], ['recibido' => ['x'], 'exacto' => '0']] as $d) {
            $this->assertSame(422, $this->post('/pos/cobrar', $this->datos($d))->estado);
        }
        DB::consulta('UPDATE leyendas SET activo = 0');
        $this->assertSame(422, $this->post('/pos/cobrar', $this->datos())->estado);
        foreach (['ventas', 'pagos', 'facturas', 'detalle_venta', 'correlativos'] as $tabla) $this->assertSame(0, (int) DB::valor('SELECT COUNT(*) FROM ' . $tabla));
    }

    public function testNoCobraAgotadosInactivosNiCategoriaInactiva(): void
    {
        DB::consulta('UPDATE productos SET agotado = 1 WHERE id = ?', [$this->producto]);
        $this->assertSame(422, $this->post('/pos/cobrar', $this->datos())->estado);
        DB::consulta('UPDATE productos SET agotado = 0, activo = 0 WHERE id = ?', [$this->producto]);
        $this->assertSame(422, $this->post('/pos/cobrar', $this->datos())->estado);
        DB::consulta('UPDATE productos SET activo = 1 WHERE id = ?', [$this->producto]);
        DB::consulta('UPDATE categorias SET activo = 0 WHERE id = (SELECT categoria_id FROM productos WHERE id = ?)', [$this->producto]);
        $this->assertSame(422, $this->post('/pos/cobrar', $this->datos())->estado);
        $this->assertSame(0, (int) DB::valor('SELECT COUNT(*) FROM ventas'));
    }

    public function testPrecioSeCalculaEnServidorYPermiteLlevar(): void
    {
        $d = $this->cobrar(['tipo' => 'llevar', 'mesa' => '', 'lineas' => json_encode([['id' => $this->producto, 'cantidad' => 1, 'precio' => '0.01']])]);
        $f = Factura::buscar($d['id']);
        $this->assertSame('35.00', $f['monto_total']);
        $this->assertNull($f['snapshot']['mesa']);
    }

    public function testCajaPermisosCsrfYPrivacidad(): void
    {
        $f = $this->cobrar();
        $this->assertSame(403, $this->post('/pos/cobrar', $this->datos(['_csrf' => 'mal']))->estado);
        $this->assertSame(404, $this->get('/facturas/99999')->estado);
        $this->entrarComo('jorge', 'pin');
        $this->assertSame(403, $this->get($f['tickets'])->estado);
        $this->assertSame(403, $this->post($f['tickets'] . '/reimpresion')->estado);
        $this->assertSame(422, $this->post('/pos/cobrar', $this->datos())->estado);
        $this->entrarComo('luis');
        $this->assertSame(403, $this->post('/pos/cobrar', $this->datos())->estado);
        $this->entrarComo('mariela', 'pin');
        DB::consulta("UPDATE cajas SET estado = 'cerrada' WHERE id = ?", [$this->caja]);
        $r = App::crear()->manejar(new Peticion('POST', '/pos/cobrar', [], $this->datos() + ['_csrf' => Csrf::token()], [], '', ['accept' => 'application/json']));
        $this->assertSame(409, $r->estado);
        $this->assertSame(1, (int) DB::valor('SELECT COUNT(*) FROM ventas'));
    }

    public function testReimpresionYVerificacionNoCambianLeyenda(): void
    {
        $d = $this->cobrar(); $f = Factura::buscar($d['id']);
        $this->assertSame(200, $this->post($d['tickets'] . '/reimpresion')->estado);
        $this->assertSame(1, (int) DB::valor("SELECT COUNT(*) FROM bitacora WHERE accion = 'factura.reimpresion'"));
        $q = ['nit' => $f['snapshot']['negocio']['nit'], 'cuf' => $f['cuf'], 'numero' => (string) $f['numero'], 'monto' => $f['monto_total']];
        $this->assertSame(200, $this->get('/verificar', $q)->estado);
        $this->assertSame(404, $this->get('/verificar', ['monto' => '0.01'] + $q)->estado);
        $this->assertSame($f['snapshot_json'], Factura::buscar($d['id'])['snapshot_json']);
    }
}
