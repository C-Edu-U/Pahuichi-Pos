<?php

declare(strict_types=1);

namespace Tests\Integracion;

use App\Core\App;
use App\Core\Csrf;
use App\Core\DB;
use App\Core\Peticion;
use Tests\CasoPrueba;

final class FotoProductoTest extends CasoPrueba
{
    private function datos(): array
    {
        return ['codigo' => 'FOTO-15', 'nombre' => 'Producto con foto', 'nombre_corto' => 'Con foto',
            'categoria_id' => (string) DB::valor('SELECT id FROM categorias LIMIT 1'),
            'precio' => '20', 'unidad' => 'plato', 'activo' => '1'];
    }

    public function testMuestraElDisenoDeDemoConCamaraYFiltroDeImagenes(): void
    {
        $this->entrarComo('admin'); $r = $this->get('/catalogo/productos/nuevo');
        $this->assertSame(200, $r->estado);
        $this->assertStringContainsString('enctype="multipart/form-data"', $r->cuerpo);
        $this->assertStringContainsString('id="zona-foto"', $r->cuerpo);
        $this->assertStringContainsString('id="camara-video"', $r->cuerpo);
        $this->assertStringContainsString('accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"', $r->cuerpo);
        $this->assertStringNotContainsString('Próximamente · HU-15', $r->cuerpo);
    }

    public function testFotoOpcionalNoImpideCrearNiEditarProducto(): void
    {
        $this->entrarComo('admin');
        $this->assertSame(303, $this->post('/catalogo/productos/nuevo', $this->datos())->estado);
        $p = DB::fila("SELECT * FROM productos WHERE codigo = 'FOTO-15'");
        $this->assertNull($p['foto']);
        $this->assertSame(303, $this->post('/catalogo/productos/' . $p['id'], $this->datos())->estado);
    }

    public function testRechazaFotoConErrorSinCrearProducto(): void
    {
        $this->entrarComo('admin');
        $r = App::crear()->manejar(new Peticion('POST', '/catalogo/productos/nuevo', [],
            $this->datos() + ['_csrf' => Csrf::token()],
            ['foto' => ['error' => UPLOAD_ERR_INI_SIZE, 'tmp_name' => '', 'size' => 0, 'name' => 'foto.jpg']]));
        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString('límite de 10 MB', $r->cuerpo);
        $this->assertNull(DB::valor("SELECT id FROM productos WHERE codigo = 'FOTO-15'"));
    }

    public function testQuitarFotoPersisteYNoCambiaOtrosProductos(): void
    {
        $this->entrarComo('admin');
        $id = (int) DB::valor("SELECT id FROM productos WHERE codigo = 'PL-01'");
        DB::consulta('UPDATE productos SET foto = ? WHERE id = ?', ['foto-0123456789abcdef.jpg', $id]);
        $this->assertSame(303, $this->post('/catalogo/productos/' . $id, $this->datos() + ['foto_quitar' => '1'])->estado);
        $this->assertNull(DB::valor('SELECT foto FROM productos WHERE id = ?', [$id]));
        $this->assertStringNotContainsString('data-actual=', $this->get('/catalogo/productos/' . $id . '/editar')->cuerpo);
    }

    public function testCajeroNoPuedeCambiarFotosYNadiePuedeLeerRutasArbitrarias(): void
    {
        $this->entrarComo('mariela', 'pin');
        $this->assertSame(403, $this->post('/catalogo/productos/1', ['foto_quitar' => '1'])->estado);
        $this->assertSame(404, $this->get('/catalogo/productos/999999/foto')->estado);
    }
}
