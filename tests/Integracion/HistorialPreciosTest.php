<?php

declare(strict_types=1);
namespace Tests\Integracion;

use App\Core\DB;
use App\Models\Bitacora;
use App\Models\Factura;
use App\Models\Producto;
use Tests\CasoPrueba;

/** HU-19: escenarios de Jira, permisos, paginación y escritura atómica. */
final class HistorialPreciosTest extends CasoPrueba
{
    private int $id;
    private array $admin;

    public static function setUpBeforeClass(): void
    {
        DB::consulta("CREATE TRIGGER prueba_hu19_auditoria BEFORE INSERT ON bitacora FOR EACH ROW
            BEGIN IF @hu19_fallar_auditoria = 1 AND NEW.accion = 'producto.precio' THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Fallo de auditoria simulado HU19'; END IF; END");
    }

    public static function tearDownAfterClass(): void
    {
        DB::consulta('DROP TRIGGER IF EXISTS prueba_hu19_auditoria');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->entrarComo('admin');
        $this->id = (int) DB::valor("SELECT id FROM productos WHERE codigo = 'PL-01'");
        DB::consulta('UPDATE productos SET precio = 38 WHERE id = ?', [$this->id]);
    }

    private function datos(string $precio): array
    {
        $p = Producto::buscar($this->id);
        return ['nombre' => $p['nombre'], 'nombre_corto' => $p['nombre_corto'], 'descripcion' => $p['descripcion'] ?? '',
            'categoria_id' => (string) $p['categoria_id'], 'precio' => $precio, 'unidad' => $p['unidad'],
            'activo' => '1', 'va_a_cocina' => '1'];
    }

    public function testEscenarioUnoDe38A40RegistraUsuarioFechaYAmbosPrecios(): void
    {
        $this->assertRedirige('/catalogo/productos', $this->post('/catalogo/productos/' . $this->id, $this->datos('40')));
        $r = Bitacora::ultimos('producto.precio');
        $this->assertCount(1, $r);
        $this->assertSame((int) $this->admin['id'], (int) $r[0]['usuario_id']);
        $this->assertSame((string) $this->id, $r[0]['entidad_id']);
        $this->assertSame(['codigo' => 'PL-01', 'antes' => '38.00', 'despues' => '40.00'], json_decode($r[0]['detalle'], true));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(?:\.\d+)?$/', $r[0]['creado_en']);
        $html = $this->get('/catalogo/productos/' . $this->id . '/editar')->cuerpo;
        foreach (['38,00', '40,00', $this->admin['nombre'], '1 cambios registrados'] as $texto) $this->assertStringContainsString($texto, $html);
    }

    public function testMismoPrecioYErrorDeValidacionNoCreanCambio(): void
    {
        $this->post('/catalogo/productos/' . $this->id, $this->datos('38,00'));
        $this->assertCount(0, Bitacora::ultimos('producto.precio'));
        $this->assertSame(422, $this->post('/catalogo/productos/' . $this->id, $this->datos('0'))->estado);
        $this->assertCount(0, Bitacora::ultimos('producto.precio'));
        $this->assertSame('38.00', Producto::buscar($this->id)['precio']);
    }

    public function testSiFallaLaAuditoriaTampocoSeCambiaElPrecio(): void
    {
        DB::consulta('SET @hu19_fallar_auditoria = 1');
        try {
            $this->assertSame(500, $this->post('/catalogo/productos/' . $this->id, $this->datos('40'))->estado);
            $this->assertSame('38.00', Producto::buscar($this->id)['precio']);
            $this->assertCount(0, Bitacora::ultimos('producto.precio'));
            $this->assertCount(0, Bitacora::ultimos('producto.edicion'));
        } finally { DB::consulta('SET @hu19_fallar_auditoria = 0'); }
    }

    public function testMasDeDiezCambiosSonConsultablesSinMezclarOtrasEntidades(): void
    {
        for ($precio = 39; $precio <= 50; $precio++) $this->post('/catalogo/productos/' . $this->id, $this->datos((string) $precio));
        Bitacora::registrar('producto.precio', 'categorias', $this->id, ['antes' => '888.00', 'despues' => '999.00']);
        $h = Producto::historialPrecios($this->id);
        $this->assertSame(12, $h['total']);
        $this->assertCount(10, $h['filas']);
        $this->assertSame('50.00', json_decode($h['filas'][0]['detalle'], true)['despues']);
        $h = Producto::historialPrecios($this->id, 999);
        $this->assertSame(2, $h['pagina']);
        $this->assertCount(2, $h['filas']);
        $this->assertSame('38.00', json_decode($h['filas'][1]['detalle'], true)['antes']);
        $r = $this->get('/catalogo/productos/' . $this->id . '/editar', ['historial' => '2']);
        $this->assertStringContainsString('Página 2 de 2', $r->cuerpo);
        $this->assertStringContainsString('38,00', $r->cuerpo);
        $this->assertStringNotContainsString('999,00', $r->cuerpo);
    }

    public function testSoloAdministradorConsultaYModificaElHistorial(): void
    {
        $this->entrarComo('mariela', 'pin');
        $this->assertSame(403, $this->get('/catalogo/productos/' . $this->id . '/editar')->estado);
        $this->assertSame(403, $this->post('/catalogo/productos/' . $this->id, $this->datos('40'))->estado);
        $this->assertSame('38.00', Producto::buscar($this->id)['precio']);
        $this->assertCount(0, Bitacora::ultimos('producto.precio'));
    }

    private function cobrar(string $total): array
    {
        $r = $this->post('/pos/cobrar', ['uuid' => '12345678-1234-4123-a123-' . bin2hex(random_bytes(6)),
            'caja_id' => (string) DB::valor("SELECT id FROM cajas WHERE estado = 'abierta'"), 'tipo' => 'llevar',
            'mesa' => '', 'exacto' => '1', 'total_esperado' => $total, 'promociones' => '0.00',
            'lineas' => json_encode([['id' => $this->id, 'cantidad' => 1, 'nota' => '']])]);
        $this->assertSame(200, $r->estado, $r->cuerpo);
        return Factura::buscar((int) json_decode($r->cuerpo, true)['id']);
    }

    public function testEscenarioDosVentasAnterioresConservanSuPrecioYLaNuevaUsa40(): void
    {
        $this->entrarComo('mariela', 'pin');
        $this->post('/caja/abrir', ['turno_id' => (int) DB::valor("SELECT id FROM turnos WHERE clave = 'mediodia'"), 'fondo' => '200']);
        $vieja = $this->cobrar('38.00');
        $antes = DB::fila('SELECT * FROM detalle_venta WHERE venta_id = ?', [$vieja['venta_id']]);
        $this->entrarComo('admin');
        $this->post('/catalogo/productos/' . $this->id, $this->datos('40'));
        $this->assertSame($antes, DB::fila('SELECT * FROM detalle_venta WHERE venta_id = ?', [$vieja['venta_id']]));
        $this->assertSame($vieja['snapshot'], Factura::buscar((int) $vieja['id'])['snapshot']);
        $this->assertSame('38.00', DB::valor('SELECT monto FROM pagos WHERE venta_id = ?', [$vieja['venta_id']]));
        $html = $this->get('/facturas/' . $vieja['id'])->cuerpo;
        $this->assertStringContainsString('38,00', $html);
        $this->assertSame(200, $this->get('/facturas/' . $vieja['id'] . '/pdf', ['seleccion' => 'todos'])->estado);
        $this->entrarComo('mariela', 'pin');
        $nueva = $this->cobrar('40.00');
        $this->assertSame('40.00', DB::valor('SELECT precio_unitario FROM detalle_venta WHERE venta_id = ?', [$nueva['venta_id']]));
    }
}
