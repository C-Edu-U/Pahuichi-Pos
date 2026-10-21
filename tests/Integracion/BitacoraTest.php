<?php

declare(strict_types=1);

namespace Tests\Integracion;

use App\Core\DB;
use App\Models\Bitacora;
use App\Services\BitacoraTexto;
use PDOException;
use Tests\CasoPrueba;

/** HU-05: bitácora de acciones sensibles, sin borrado. */
final class BitacoraTest extends CasoPrueba
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->entrarComo('admin');
    }

    private function id(string $usuario): int
    {
        return (int) DB::valor('SELECT id FROM usuarios WHERE usuario = ?', [$usuario]);
    }

    /** Registro con fecha fija, para probar los filtros. */
    private function registro(string $accion, string $creadoEn, ?string $usuario = 'admin', ?array $detalle = null, ?string $ip = '10.0.0.1'): void
    {
        DB::insertar(
            'INSERT INTO bitacora (usuario_id, accion, detalle, ip, creado_en) VALUES (?, ?, ?, ?, ?)',
            [$usuario === null ? null : $this->id($usuario), $accion, $detalle === null ? null : json_encode($detalle, JSON_UNESCAPED_UNICODE), $ip, $creadoEn],
        );
    }

    /** Acciones de las filas de la tabla, en el orden en que se ven. */
    private function acciones(string $html): array
    {
        preg_match_all('/<tr data-accion="([a-z_.]+)">/', $html, $m);
        return $m[1];
    }

    /**
     * Escenario: El administrador consulta la bitácora
     *   Dado que se cambió el precio de un producto
     *   Cuando el administrador abre la bitácora
     *   Entonces ve quién lo cambió, cuándo, el producto y el precio anterior y el nuevo
     */
    public function testMuestraElCambioDePrecioLegible(): void
    {
        $id = (int) DB::valor("SELECT id FROM productos WHERE codigo = 'PL-01'");
        $this->post("/catalogo/productos/{$id}", [
            'nombre' => 'Majadito de charque', 'nombre_corto' => 'Majadito', 'descripcion' => '',
            'categoria_id' => DB::valor("SELECT id FROM categorias WHERE nombre = 'Platos'"),
            'precio' => '42', 'unidad' => 'plato', 'activo' => '1', 'va_a_cocina' => '1',
        ]);

        $r = $this->get('/bitacora');

        $this->assertSame(200, $r->estado);
        $this->assertStringContainsString('<span class="insignia insignia--aviso">Cambio de precio</span>', $r->cuerpo);
        $this->assertStringContainsString('Producto: PL-01 · Majadito de charque', $r->cuerpo);
        $this->assertStringContainsString('Bs 40,00 → Bs 42,00', $r->cuerpo);
        $this->assertStringContainsString('Ana Lucía Vaca Saucedo', $r->cuerpo);
    }

    public function testLoMasNuevoPrimero(): void
    {
        $this->registro('caja.apertura', '2026-10-15 11:00:00');
        $this->registro('caja.cierre', '2026-10-15 16:05:00');

        $this->assertSame(['caja.cierre', 'caja.apertura'], array_slice($this->acciones($this->get('/bitacora')->cuerpo), 0, 2));
    }

    /**
     * Escenario: Filtrar las acciones sensibles
     *   Dado que hubo inicios de sesión, una apertura de caja y un cambio de precio
     *   Cuando el administrador filtra por acciones sensibles
     *   Entonces ve la apertura y el cambio de precio, y no los inicios de sesión
     */
    public function testFiltroDeAccionesSensibles(): void
    {
        $this->registro('sesion.inicio', '2026-10-15 10:00:00');
        $this->registro('caja.apertura', '2026-10-15 11:00:00');
        $this->registro('producto.precio', '2026-10-15 12:00:00', 'admin', ['codigo' => 'PL-01', 'antes' => '40.00', 'despues' => '42.00']);

        $acciones = $this->acciones($this->get('/bitacora', ['accion' => 'sensibles'])->cuerpo);

        $this->assertSame(['producto.precio', 'caja.apertura'], $acciones);
    }

    public function testFiltroPorAccionYPorGrupo(): void
    {
        $this->registro('usuario.alta', '2026-10-15 09:00:00');
        $this->registro('usuario.rol', '2026-10-15 09:05:00');
        $this->registro('categoria.alta', '2026-10-15 09:10:00');

        $this->assertSame(['usuario.rol'], $this->acciones($this->get('/bitacora', ['accion' => 'usuario.rol'])->cuerpo));
        $this->assertSame(['usuario.rol', 'usuario.alta'], array_values(array_filter(
            $this->acciones($this->get('/bitacora', ['accion' => 'grupo:usuarios'])->cuerpo),
            static fn (string $a): bool => str_starts_with($a, 'usuario.'),
        )));
    }

    public function testFiltroPorUsuarioYSinUsuario(): void
    {
        $this->registro('sesion.fallo', '2026-10-15 09:00:00', null, ['modo' => 'pin'], '10.0.0.9');
        $this->registro('caja.apertura', '2026-10-15 11:00:00', 'mariela');

        $this->assertSame(['caja.apertura'], $this->acciones($this->get('/bitacora', ['usuario' => (string) $this->id('mariela')])->cuerpo));

        $r = $this->get('/bitacora', ['usuario' => 'ninguno']);
        $this->assertSame(['sesion.fallo'], $this->acciones($r->cuerpo));
        $this->assertStringContainsString('Sin usuario', $r->cuerpo);
        $this->assertStringContainsString('Con: PIN', $r->cuerpo);
    }

    public function testFiltroPorFechas(): void
    {
        $this->registro('caja.apertura', '2026-10-13 11:00:00');
        $this->registro('caja.apertura', '2026-10-14 11:00:00');
        $this->registro('caja.cierre', '2026-10-14 23:59:59');
        $this->registro('caja.apertura', '2026-10-15 11:00:00');

        $r = $this->get('/bitacora', ['desde' => '2026-10-14', 'hasta' => '2026-10-14']);

        $this->assertSame(['caja.cierre', 'caja.apertura'], $this->acciones($r->cuerpo));
        $this->assertStringContainsString('<span class="tabla__principal">14/10/2026</span><span class="tabla__secundario">23:59:59</span>', $r->cuerpo);
        // Fechas al revés: se ordenan solas (del 13 al 14)
        $this->assertCount(3, $this->acciones($this->get('/bitacora', ['desde' => '2026-10-14', 'hasta' => '2026-10-13'])->cuerpo));
    }

    public function testBusquedaEnDetalleEIp(): void
    {
        $this->registro('config.negocio', '2026-10-15 09:00:00', 'admin', ['direccion' => ['antes' => 'Calle por confirmar', 'despues' => 'Av. Cañoto 345']]);
        $this->registro('sesion.inicio', '2026-10-15 09:01:00', 'admin', null, '192.168.1.77');

        $this->assertSame(['config.negocio'], $this->acciones($this->get('/bitacora', ['buscar' => 'cañoto'])->cuerpo));
        $this->assertSame(['sesion.inicio'], $this->acciones($this->get('/bitacora', ['buscar' => '192.168.1.77'])->cuerpo));
        $this->assertStringContainsString('Dirección: Calle por confirmar → Av. Cañoto 345', $this->get('/bitacora', ['buscar' => 'cañoto'])->cuerpo);
    }

    public function testPaginaDe50Registros(): void
    {
        // La bitácora no se vacía: se cuenta lo que ya tenga
        $antes = (int) DB::valor('SELECT COUNT(*) FROM bitacora');
        for ($i = 0; $i < 60; $i++) {
            $this->registro('sesion.inicio', sprintf('2026-10-15 10:%02d:00', $i));
        }
        $total = $antes + 60;

        $p1 = $this->get('/bitacora');
        $this->assertCount(50, $this->acciones($p1->cuerpo));
        $this->assertStringContainsString("1 a 50 de {$total} registros", $p1->cuerpo);
        $this->assertStringContainsString('Más viejos', $p1->cuerpo);

        $p2 = $this->get('/bitacora', ['pagina' => '2']);
        $this->assertCount($total - 50, $this->acciones($p2->cuerpo));
        $this->assertStringContainsString('Más nuevos', $p2->cuerpo);
    }

    public function testElDetalleSeEscapa(): void
    {
        $this->registro('producto.alta', '2026-10-15 09:00:00', 'admin', ['nombre' => '<script>alert(1)</script>']);

        $r = $this->get('/bitacora');

        $this->assertStringNotContainsString('<script>alert(1)</script>', $r->cuerpo);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $r->cuerpo);
    }

    public function testFiltrosInvalidosSeIgnoran(): void
    {
        $r = $this->get('/bitacora', ['desde' => 'ayer', 'usuario' => "1' OR 1=1", 'accion' => 'DROP TABLE', 'pagina' => '-3']);

        $this->assertSame(200, $r->estado);
    }

    public function testAccionDesconocidaSeVeConSuNombre(): void
    {
        $this->registro('modulo.nuevo', '2026-10-15 09:00:00');

        $this->assertStringContainsString('<span class="insignia insignia--neutra">modulo.nuevo</span>', $this->get('/bitacora')->cuerpo);
    }

    /**
     * Escenario: La bitácora no se borra
     *   Dado un registro en la bitácora
     *   Cuando alguien intenta editarlo o borrarlo
     *   Entonces la base lo rechaza, y la pantalla no ofrece cómo hacerlo
     */
    public function testNoSeEditaNiSeBorra(): void
    {
        $this->registro('caja.apertura', '2026-10-15 11:00:00');

        $this->assertSame(405, $this->post('/bitacora', ['accion' => 'borrar'])->estado);
        // El único formulario POST de la página es el de cerrar sesión
        preg_match_all('/<form[^>]*method="post"[^>]*>/', $this->get('/bitacora')->cuerpo, $formularios);
        $this->assertSame(['<form id="form-salir" method="post" action="/salir">'], $formularios[0]);
        $this->expectException(PDOException::class);
        DB::consulta("DELETE FROM bitacora WHERE accion = 'caja.apertura'");
    }

    public function testSoloElAdministrador(): void
    {
        $this->entrarComo('mariela', 'pin');

        $this->assertSame(403, $this->get('/bitacora')->estado);
        $this->assertNotEmpty(Bitacora::ultimos('acceso.denegado'));
    }

    public function testElMenuDelAdministradorYaNoDiceProximamente(): void
    {
        $this->assertStringNotContainsString('Próximamente', $this->get('/bitacora')->cuerpo);
    }

    public function testTextoDelDetalle(): void
    {
        $this->assertSame('mesero → cajero', BitacoraTexto::detalle('{"antes":"mesero","despues":"cajero"}'));
        $this->assertSame(
            'Orden: 5 → 2 · Color: #3f6f1f → #8e2c7c',
            BitacoraTexto::detalle('{"antes":{"nombre":"Postres","orden":5,"color":"#3f6f1f"},"despues":{"nombre":"Postres","orden":"2","color":"#8e2c7c"}}'),
        );
        $this->assertSame('Favorito: sí · Código: PL-05', BitacoraTexto::detalle('{"favorito":true,"codigo":"PL-05"}'));
        $this->assertSame('', BitacoraTexto::detalle(null));
    }
}
