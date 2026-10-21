<?php

declare(strict_types=1);

namespace Tests\Integracion;

use App\Core\DB;
use App\Core\Reloj;
use Tests\CasoPrueba;

/** HU-28: cuadrícula de productos del punto de venta. */
final class CuadriculaPosTest extends CasoPrueba
{
    protected function setUp(): void
    {
        parent::setUp();
        Reloj::fijar('2026-10-19 12:00:00');
        $this->entrarComo('mariela', 'pin');
        $turno = (int) DB::valor("SELECT id FROM turnos WHERE clave = 'mediodia'");
        $this->post('/caja/abrir', ['turno_id' => $turno, 'fondo' => '300']);
    }

    private function pos(): string
    {
        $r = $this->get('/pos');
        $this->assertSame(200, $r->estado);
        return $r->cuerpo;
    }

    private function id(string $codigo): int
    {
        return (int) DB::valor('SELECT id FROM productos WHERE codigo = ?', [$codigo]);
    }

    /** Ids de los productos en el orden en que aparecen en la cuadrícula. */
    private function ids(string $html): array
    {
        preg_match_all('/<button class="producto-pos [^"]*" type="button" data-id="(\d+)"/', $html, $m);
        return array_map('intval', $m[1]);
    }

    /**
     * Escenario: La primera pantalla son los favoritos
     *   Dado que hay una caja abierta y productos marcados como favoritos
     *   Cuando el cajero entra al punto de venta
     *   Entonces ve primero los favoritos, con su foto o ícono, nombre corto y precio
     */
    public function testLosFavoritosSonLaPrimeraPantalla(): void
    {
        $html = $this->pos();

        $this->assertMatchesRegularExpression('/data-categoria="favoritos" aria-pressed="true"/', $html);
        $favoritos = array_map('intval', array_column(DB::filas('SELECT id FROM productos WHERE favorito = 1 AND activo = 1'), 'id'));
        $this->assertCount(8, $favoritos);
        $this->assertEqualsCanonicalizing($favoritos, array_slice($this->ids($html), 0, 8), 'Los favoritos van primero');
        // Los que no son favoritos se dibujan ocultos hasta cambiar de pestaña
        $this->assertSame(25 - 8, preg_match_all('/data-favorito="0" data-busqueda="[^"]*" hidden>/', $html));
        $this->assertStringContainsString('<span class="producto-pos__nombre">Majadito</span>', $html);
        $this->assertStringContainsString('<span class="monto__moneda">Bs</span>40,00', $html);
    }

    public function testMuestraTodosLosProductosActivos(): void
    {
        $this->assertCount(25, $this->ids($this->pos()));
    }

    public function testCategoriasEnSuOrdenYConSuColor(): void
    {
        $html = $this->pos();

        preg_match_all('/<button class="categoria-pos ([^"]+)" type="button" data-categoria="(\w+)"[^>]*>(?:<svg.*?<\/svg>)?([^<]+)</', $html, $m);
        $this->assertSame(['Favoritos', 'Platos', 'Refrescos', 'Sodas', 'Vinos', 'Postres', 'Prueba', 'Todo'], $m[3]);
        $this->assertSame('cat--ambar', $m[1][1]);
        $this->assertStringNotContainsString('style=', $html, 'Sin estilos en línea (CSP)');
    }

    public function testNoAparecenProductosInactivosNiCategoriasDesactivadas(): void
    {
        DB::consulta("UPDATE productos SET activo = 0 WHERE codigo = 'PL-05'");
        DB::consulta("UPDATE categorias SET activo = 0 WHERE nombre = 'Vinos'");

        $html = $this->pos();

        $this->assertNotContains($this->id('PL-05'), $this->ids($html));
        $this->assertNotContains($this->id('VI-01'), $this->ids($html));
        $this->assertCount(25 - 1 - 3, $this->ids($html));
        $this->assertStringNotContainsString('>Vinos<', $html);
    }

    public function testElAgotadoSeVePeroNoSePuedeTocar(): void
    {
        $html = $this->pos();

        $this->assertMatchesRegularExpression('/data-id="' . $this->id('PL-07') . '" disabled aria-label="Fricasé de cerdo, 40,00 bolivianos, agotado"/', $html);
        $this->assertStringContainsString('<span class="insignia insignia--aviso">Agotado</span>', $html);
    }

    public function testBuscaPorNombreNombreCortoYCodigo(): void
    {
        $this->assertStringContainsString('data-busqueda="Majadito de charque Majadito PL-01"', $this->pos());
    }

    public function testEntregaLosDatosQueUsaElCarrito(): void
    {
        preg_match('/data-productos="([^"]+)"/', $this->pos(), $m);
        $datos = json_decode(html_entity_decode($m[1]), true);

        $majadito = array_values(array_filter($datos, fn (array $p): bool => $p['codigo'] === 'PL-01'))[0];
        // JSON escribe 40.0 como 40: se compara el valor, no el tipo
        $this->assertEquals(['id' => $this->id('PL-01'), 'codigo' => 'PL-01', 'nombre' => 'Majadito de charque', 'corto' => 'Majadito', 'precio' => 40, 'foto' => null, 'agotado' => false, 'cocina' => true], $majadito);
    }

    public function testSinFavoritosAbreEnTodo(): void
    {
        DB::consulta('UPDATE productos SET favorito = 0');

        $html = $this->pos();

        $this->assertStringNotContainsString('data-categoria="favoritos"', $html);
        $this->assertMatchesRegularExpression('/data-categoria="todo" aria-pressed="true"/', $html);
        $this->assertSame(0, preg_match_all('/data-busqueda="[^"]*" hidden>/', $html), 'Ningún producto oculto');
    }

    public function testConFotoMuestraLaImagen(): void
    {
        DB::consulta("UPDATE productos SET foto = 'producto-0123456789abcdef.jpg' WHERE codigo = 'PL-01'");

        $this->assertStringContainsString('<img src="/catalogo/productos/' . $this->id('PL-01') . '/foto?v=producto-0123456789abcdef.jpg" alt="" loading="lazy">', $this->pos());
    }

    public function testElAdministradorTambienVende(): void
    {
        $this->entrarComo('admin');

        $this->assertCount(25, $this->ids($this->pos()));
    }
}
