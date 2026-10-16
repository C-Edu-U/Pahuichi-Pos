<?php

declare(strict_types=1);

namespace Tests\Integracion;

use App\Core\DB;
use App\Models\Bitacora;
use Tests\CasoPrueba;

/** HU-13: categorías con orden y color de botón. */
final class CategoriasTest extends CasoPrueba
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->entrarComo('admin');
    }

    public function testListaEnOrdenConSuColorYCantidadDeProductos(): void
    {
        $r = $this->get('/catalogo/categorias');

        $this->assertSame(200, $r->estado);
        preg_match_all('/categoria-pos--tabla (cat--[a-z]+)" aria-pressed="true">([^<]+)</', $r->cuerpo, $m);
        $this->assertSame(['Platos', 'Refrescos', 'Sodas', 'Vinos', 'Postres', 'Prueba'], $m[2]);
        $this->assertSame('cat--ambar', $m[1][0]);
        $this->assertStringNotContainsString('style=', $r->cuerpo, 'Sin estilos en línea (CSP)');
    }

    public function testAgregaUnaCategoria(): void
    {
        $r = $this->post('/catalogo/categorias', ['nombre' => '  Desayunos ', 'orden' => '7', 'color' => '#4a6fb5']);

        $this->assertRedirige('/catalogo/categorias', $r);
        $c = DB::fila("SELECT * FROM categorias WHERE nombre = 'Desayunos'");
        $this->assertSame(7, (int) $c['orden']);
        $this->assertSame('#4a6fb5', $c['color']);
        $this->assertCount(1, Bitacora::ultimos('categoria.alta'));
    }

    public function testNombreRepetidoSinImportarMayusculasNiTildes(): void
    {
        $r = $this->post('/catalogo/categorias', ['nombre' => 'PLATOS', 'orden' => '7', 'color' => '#4a6fb5']);

        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString('Ya existe la categoría', $r->cuerpo);
    }

    public function testSoloColoresDeLaPaleta(): void
    {
        $r = $this->post('/catalogo/categorias', ['nombre' => 'Fiesta', 'orden' => '0', 'color' => '#ffff00']);

        $this->assertStringContainsString('Elige un color de la paleta.', $r->cuerpo);
        $this->assertStringContainsString('El orden es un número de 1 a 99.', $r->cuerpo);
        $this->assertNull(DB::valor("SELECT id FROM categorias WHERE nombre = 'Fiesta'"));
    }

    public function testEditaOrdenYColor(): void
    {
        $id = (int) DB::valor("SELECT id FROM categorias WHERE nombre = 'Postres'");

        $this->assertStringContainsString('Editar «Postres»', $this->get("/catalogo/categorias/{$id}/editar")->cuerpo);
        $this->post("/catalogo/categorias/{$id}", ['nombre' => 'Postres', 'orden' => '2', 'color' => '#8e2c7c']);

        $c = DB::fila('SELECT orden, color FROM categorias WHERE id = ?', [$id]);
        $this->assertSame([2, '#8e2c7c'], [(int) $c['orden'], $c['color']]);
    }

    public function testDesactivarYActivar(): void
    {
        $id = (int) DB::valor("SELECT id FROM categorias WHERE nombre = 'Vinos'");

        $this->post("/catalogo/categorias/{$id}/estado", ['activo' => '0']);
        $this->assertSame(0, (int) DB::valor('SELECT activo FROM categorias WHERE id = ?', [$id]));
        $this->post("/catalogo/categorias/{$id}/estado", ['activo' => '1']);
        $this->assertSame(1, (int) DB::valor('SELECT activo FROM categorias WHERE id = ?', [$id]));
    }

    public function testElCajeroLasVePeroNoLasEdita(): void
    {
        $this->entrarComo('mariela', 'pin');

        $r = $this->get('/catalogo/categorias');
        $this->assertSame(200, $r->estado);
        $this->assertStringNotContainsString('form-categoria', $r->cuerpo);
        $this->assertSame(403, $this->post('/catalogo/categorias', ['nombre' => 'X', 'orden' => '7', 'color' => '#4a6fb5'])->estado);
    }
}
