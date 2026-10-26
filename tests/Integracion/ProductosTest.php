<?php

declare(strict_types=1);

namespace Tests\Integracion;

use App\Core\DB;
use App\Models\Bitacora;
use Tests\CasoPrueba;

/** HU-14: productos. */
final class ProductosTest extends CasoPrueba
{
    private int $platos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entrarComo('admin');
        $this->platos = (int) DB::valor("SELECT id FROM categorias WHERE nombre = 'Platos'");
    }

    private function datos(array $cambios = []): array
    {
        return $cambios + [
            'codigo' => 'pl-10',
            'nombre' => 'Masaco de plátano',
            'nombre_corto' => 'Masaco',
            'descripcion' => 'Con charque y queso',
            'categoria_id' => (string) $this->platos,
            'precio' => '32,50',
            'unidad' => 'plato',
            'activo' => '1',
            'va_a_cocina' => '1',
        ];
    }

    private function id(string $codigo): int
    {
        return (int) DB::valor('SELECT id FROM productos WHERE codigo = ?', [$codigo]);
    }

    public function testListaLos25ProductosConSuCategoria(): void
    {
        $r = $this->get('/catalogo/productos');

        $this->assertSame(200, $r->estado);
        $this->assertSame(25, substr_count($r->cuerpo, 'data-busqueda='));
        $this->assertStringContainsString('Majadito de charque', $r->cuerpo);
        $this->assertStringContainsString('<span class="monto__moneda">Bs</span>120,00', $r->cuerpo);
        $this->assertStringNotContainsString('style=', $r->cuerpo);
    }

    public function testAltaDeProducto(): void
    {
        $r = $this->post('/catalogo/productos/nuevo', $this->datos());

        $this->assertRedirige('/catalogo/productos', $r);
        $p = DB::fila("SELECT * FROM productos WHERE codigo = 'PL-10'");
        $this->assertSame('32.50', $p['precio']);
        $this->assertSame('Masaco', $p['nombre_corto']);
        $this->assertSame(1, (int) $p['va_a_cocina']);
        $this->assertSame(0, (int) $p['favorito']);
        $this->assertCount(1, Bitacora::ultimos('producto.alta'));
    }

    public function testCodigoUnico(): void
    {
        $r = $this->post('/catalogo/productos/nuevo', $this->datos(['codigo' => 'PL-01']));

        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString('Ese código ya lo usa otro producto.', $r->cuerpo);
        $this->assertStringContainsString('Revisa los campos marcados', $r->cuerpo);
    }

    public function testValidaLosCampos(): void
    {
        $r = $this->post('/catalogo/productos/nuevo', $this->datos([
            'codigo' => 'con espacio', 'nombre' => '', 'nombre_corto' => 'Nombre corto muy largo', 'precio' => '0', 'unidad' => 'kilo', 'categoria_id' => '999',
        ]));

        foreach ([
            'Usa de 2 a 12 letras, números o guion',
            'Escribe el nombre del producto.',
            'Usa hasta 16 letras',
            'Escribe un precio mayor a 0',
            'Elige una unidad de la lista.',
            'Elige una categoría activa.',
        ] as $mensaje) {
            $this->assertStringContainsString($mensaje, $r->cuerpo);
        }
        $this->assertSame(25, (int) DB::valor('SELECT COUNT(*) FROM productos'));
    }

    public function testEdicionConCambioDePrecioQuedaEnLaBitacora(): void
    {
        $id = $this->id('PL-01');

        $this->assertStringContainsString('value="40,00"', $this->get("/catalogo/productos/{$id}/editar")->cuerpo);
        $this->post("/catalogo/productos/{$id}", $this->datos(['codigo' => 'OTRO', 'nombre' => 'Majadito de charque', 'precio' => '42']));

        $p = DB::fila('SELECT * FROM productos WHERE id = ?', [$id]);
        $this->assertSame('PL-01', $p['codigo'], 'El código no cambia al editar');
        $this->assertSame('42.00', $p['precio']);
        $cambio = json_decode(Bitacora::ultimos('producto.precio')[0]['detalle'], true);
        $this->assertSame(['40.00', '42.00'], [$cambio['antes'], $cambio['despues']]);
        $this->assertStringContainsString('Cambios de precio', $this->get("/catalogo/productos/{$id}/editar")->cuerpo);
    }

    public function testProductoInactivo(): void
    {
        $id = $this->id('PL-05');

        $r = $this->post("/catalogo/productos/{$id}", $this->datos(['nombre' => 'Pacumutu de res', 'activo' => '0']));

        $this->assertRedirige('/catalogo/productos', $r);
        $this->assertSame(0, (int) DB::valor('SELECT activo FROM productos WHERE id = ?', [$id]));
    }

    public function testCategoriaInactivaNoSeOfreceEnElAlta(): void
    {
        DB::consulta("UPDATE categorias SET activo = 0 WHERE nombre = 'Vinos'");

        $this->assertStringNotContainsString('>Vinos<', $this->get('/catalogo/productos/nuevo')->cuerpo);
    }

    public function testElCajeroMarcaAgotadoDeUnToque(): void
    {
        $this->entrarComo('mariela', 'pin');
        $id = $this->id('PL-02');

        $r = $this->post("/catalogo/productos/{$id}/agotado");

        $this->assertRedirige('/catalogo/productos', $r);
        $p = DB::fila('SELECT agotado, activo FROM productos WHERE id = ?', [$id]);
        $this->assertSame([1, 1], [(int) $p['agotado'], (int) $p['activo']], 'Agotado no desactiva');
        $this->post("/catalogo/productos/{$id}/agotado");
        $this->assertSame(0, (int) DB::valor('SELECT agotado FROM productos WHERE id = ?', [$id]));
    }

    public function testFavoritoRespondeJsonAlJs(): void
    {
        $id = $this->id('PL-05');
        $r = \App\Core\App::crear()->manejar(new \App\Core\Peticion(
            'POST', "/catalogo/productos/{$id}/favorito", [], ['_csrf' => \App\Core\Csrf::token()], [], '10.0.0.1', ['accept' => 'application/json'],
        ));

        $this->assertSame(['ok' => true, 'favorito' => true, 'mensaje' => 'Pacumutu de res ahora es favorito.'], json_decode($r->cuerpo, true));
    }

    public function testElCajeroNoCreaNiEdita(): void
    {
        $this->entrarComo('mariela', 'pin');

        $r = $this->get('/catalogo/productos');
        $this->assertSame(200, $r->estado);
        $this->assertStringNotContainsString('Nuevo producto', $r->cuerpo);
        $this->assertSame(403, $this->get('/catalogo/productos/nuevo')->estado);
        $this->assertSame(403, $this->post('/catalogo/productos/nuevo', $this->datos())->estado);
        $this->assertSame(403, $this->post('/catalogo/productos/' . $this->id('PL-01') . '/favorito')->estado);
    }
}
