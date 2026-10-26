<?php

declare(strict_types=1);

namespace Tests\Integracion;

use App\Core\DB;
use App\Core\Reloj;
use App\Models\Factura;
use Tests\CasoPrueba;

/** HU-18 acordada: extras como productos independientes en una sola venta. */
final class ExtrasTest extends CasoPrueba
{
    public function testExtraConPrecioPropioEnCategoriaYUnaSolaFactura(): void
    {
        Reloj::fijar('2026-10-24 12:00:00');
        $this->entrarComo('admin');
        $this->assertRedirige('/catalogo/categorias', $this->post('/catalogo/categorias', [
            'nombre' => 'Extras', 'orden' => '8', 'color' => '#13603e',
        ]));
        $categoria = (int) DB::valor("SELECT id FROM categorias WHERE nombre='Extras'");
        $this->assertGreaterThan(0, $categoria);
        $this->assertRedirige('/catalogo/productos', $this->post('/catalogo/productos/nuevo', [
            'codigo' => 'EX-01', 'nombre' => 'Porción de arroz', 'nombre_corto' => 'Arroz extra',
            'categoria_id' => (string) $categoria, 'precio' => '5', 'unidad' => 'porcion',
            'activo' => '1', 'va_a_cocina' => '1',
        ]));
        $extra = (int) DB::valor("SELECT id FROM productos WHERE codigo='EX-01'");
        $plato = (int) DB::valor("SELECT id FROM productos WHERE codigo='PL-01'");
        DB::consulta('UPDATE productos SET precio=38 WHERE id=?', [$plato]);
        $this->assertStringNotContainsString('t-modificadores', $this->get('/catalogo/productos/' . $extra . '/editar')->cuerpo);
        $this->entrarComo('mariela', 'pin');
        $this->post('/caja/abrir', ['turno_id' => (int) DB::valor("SELECT id FROM turnos WHERE clave='mediodia'"), 'fondo' => '100']);
        $html = $this->get('/pos')->cuerpo;
        $this->assertStringContainsString('Extras', $html);
        $this->assertStringContainsString('Arroz extra', $html);
        $this->assertStringNotContainsString('Modificadores', $html);
        $r = $this->post('/pos/cobrar', [
            'uuid' => '12345678-1234-4123-a123-' . bin2hex(random_bytes(6)),
            'caja_id' => (string) DB::valor("SELECT id FROM cajas WHERE estado='abierta'"),
            'tipo' => 'llevar', 'mesa' => '', 'exacto' => '1', 'total_esperado' => '43',
            'lineas' => json_encode([['id' => $plato, 'cantidad' => 1, 'nota' => ''], ['id' => $extra, 'cantidad' => 1, 'nota' => '']]),
        ]);
        $this->assertSame(200, $r->estado, $r->cuerpo);
        $id = (int) json_decode($r->cuerpo, true)['id'];
        $f = Factura::buscar($id);
        $this->assertSame(1, (int) DB::valor('SELECT COUNT(*) FROM facturas'));
        $this->assertSame('43.00', $f['monto_total']);
        $this->assertCount(2, $f['snapshot']['lineas']);
        $this->assertSame(['38.00', '5.00'], array_column(DB::filas('SELECT precio_unitario FROM detalle_venta WHERE venta_id=? ORDER BY id', [$f['venta_id']]), 'precio_unitario'));
        $html = $this->get('/facturas/' . $id)->cuerpo;
        $this->assertSame(3, substr_count($html, 'Porción de arroz'), 'Comanda, copia de caja y factura incluyen el extra.');
        foreach (['comanda', 'copia', 'factura', 'todos'] as $seleccion) {
            $pdf = $this->get('/facturas/' . $id . '/pdf', ['seleccion' => $seleccion]);
            $this->assertSame(200, $pdf->estado);
            $this->assertStringStartsWith('%PDF-', $pdf->cuerpo);
        }
        $this->assertSame(0, (int) DB::valor("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='producto_modificadores'"));
        $this->assertSame(0, (int) DB::valor("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='detalle_venta' AND column_name='modificadores_json'"));
    }
}
