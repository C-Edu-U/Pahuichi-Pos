<?php

declare(strict_types=1);
namespace Tests\Integracion;

use App\Core\DB;
use App\Models\Leyenda;
use App\Services\LeyendaService;
use Tests\CasoPrueba;

final class LeyendasTest extends CasoPrueba
{
    protected function setUp(): void { parent::setUp(); $this->entrarComo('admin'); }

    /** Escenario 1: dado un sistema recién instalado, al revisar leyendas hay al menos ocho disponibles. */
    public function testCatalogoPrecargadoYVistaDeDemo(): void
    {
        $this->assertCount(8, Leyenda::todas());
        $r = $this->get('/config');
        $this->assertSame(200, $r->estado);
        $this->assertSame(8, substr_count($r->cuerpo, 'class="leyenda-fila"'));
        $this->assertStringContainsString('Agregar leyenda', $r->cuerpo);
        $this->assertStringContainsString('role="switch"', $r->cuerpo);
        $this->assertStringNotContainsString('Probar leyenda en factura', $r->cuerpo);
    }

    /** Escenario 3: dado el catálogo, al agregar una leyenda queda guardada sin alterar las demás. */
    public function testAltaPersistenteNormalizaPrefijo(): void
    {
        $this->assertRedirige('/config#leyendas', $this->post('/config/leyendas', ['texto' => ' Ley N° 453: Nueva leyenda. ']));
        $id = (int) DB::valor('SELECT MAX(id) FROM leyendas');
        $this->assertSame('Ley N° 453: Nueva leyenda.', DB::valor('SELECT texto FROM leyendas WHERE id = ?', [$id]));
        $this->post("/config/leyendas/$id/estado", ['activo' => '0']);
        $this->post('/config/leyendas', ['texto' => 'Otra leyenda.']);
        $this->assertSame(0, (int) DB::valor('SELECT activo FROM leyendas WHERE id = ?', [$id]));
        $this->assertStringContainsString('Otra leyenda.', $this->get('/config')->cuerpo);
    }

    public function testValidaVaciaPrefijoLargaDuplicadaYArchivoArray(): void
    {
        foreach (['', 'Ley N° 453:', str_repeat('a', 245), Leyenda::todas()[0]['texto'], ['texto']] as $texto) {
            $r = $this->post('/config/leyendas', ['texto' => $texto]);
            $this->assertSame(422, $r->estado);
            $this->assertStringContainsString('id="l-texto-error"', $r->cuerpo);
        }
        $this->assertCount(8, Leyenda::todas());
    }

    /** Escenario: con ocho activas no se permite desactivar; con nueve se permite hasta quedar ocho. */
    public function testMinimoOchoYReactivar(): void
    {
        $id = (int) Leyenda::todas()[0]['id'];
        $r = $this->post("/config/leyendas/$id/estado", ['activo' => '0']);
        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString('Deben quedar al menos 8', $r->cuerpo);
        LeyendaService::agregar('Novena.');
        $this->assertRedirige('/config#leyendas', $this->post("/config/leyendas/$id/estado", ['activo' => '0']));
        $this->assertSame(8, (int) DB::valor('SELECT COUNT(*) FROM leyendas WHERE activo = 1'));
        $this->assertRedirige('/config#leyendas', $this->post("/config/leyendas/$id/estado", ['activo' => '1']));
        $this->assertSame(9, (int) DB::valor('SELECT COUNT(*) FROM leyendas WHERE activo = 1'));
        $this->assertSame(422, $this->post("/config/leyendas/$id/estado", ['activo' => 'x'])->estado);
        $this->assertSame(404, $this->post('/config/leyendas/99999/estado', ['activo' => '1'])->estado);
    }

    public function testPermisosCsrfYTextoEscapado(): void
    {
        $this->assertSame(403, $this->post('/config/leyendas', ['texto' => 'Sin CSRF', '_csrf' => 'mal'])->estado);
        $this->post('/config/leyendas', ['texto' => '<script>alert(1)</script>']);
        $this->assertStringContainsString('&lt;script&gt;', $this->get('/config')->cuerpo);
        $this->entrarComo('mariela', 'pin');
        $this->assertSame(403, $this->get('/config')->estado);
        $this->assertSame(403, $this->post('/config/leyendas', ['texto' => 'No autorizado'])->estado);
        $this->assertSame(403, $this->post('/config/leyendas/1/estado', ['activo' => '0'])->estado);
    }

    /**
     * Escenario: elección al azar para cada factura
     *   Dado un catálogo con leyendas activas e inactivas
     *   Cuando se elige la leyenda de una factura
     *   Entonces sale siempre una activa, y no siempre la misma
     */
    public function testAlAzarSoloEligeEntreLasActivas(): void
    {
        LeyendaService::agregar('Novena.');
        LeyendaService::agregar('Décima.');
        $todas = Leyenda::todas();
        LeyendaService::estado((int) $todas[0]['id'], false);
        LeyendaService::estado((int) $todas[1]['id'], false);
        $activas = array_map('intval', array_column(DB::filas('SELECT id FROM leyendas WHERE activo = 1'), 'id'));
        $this->assertCount(8, $activas);

        $elegidas = [];
        for ($i = 0; $i < 200; $i++) {
            $l = Leyenda::alAzar();
            $this->assertContains((int) $l['id'], $activas);
            $this->assertStringStartsWith('Ley N° 453: ', $l['texto']);
            $elegidas[(int) $l['id']] = true;
        }
        $this->assertGreaterThan(1, count($elegidas), 'La elección varía entre las activas');
    }

    public function testUnaLeyendaRecienAgregadaPuedeSerElegida(): void
    {
        $id = LeyendaService::agregar('Única activa en esta prueba.');
        DB::consulta('UPDATE leyendas SET activo = 0 WHERE id <> ?', [$id]);

        $l = Leyenda::alAzar();
        $this->assertSame($id, (int) $l['id']);
        $this->assertSame('Ley N° 453: Única activa en esta prueba.', $l['texto']);
    }

    public function testSinLeyendasActivasNoSeEligeNinguna(): void
    {
        DB::consulta('UPDATE leyendas SET activo = 0');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('No hay leyendas activas');
        Leyenda::alAzar();
    }

    /** Integración con HU-05: los cambios al catálogo se ven en la bitácora con su nombre. */
    public function testLosCambiosSeVenEnLaBitacora(): void
    {
        $this->post('/config/leyendas', ['texto' => 'Tienes derecho a un comprobante.']);
        $id = (int) DB::valor('SELECT MAX(id) FROM leyendas');
        $this->post("/config/leyendas/$id/estado", ['activo' => '0']);

        $r = $this->get('/bitacora', ['accion' => 'grupo:configuracion']);

        $this->assertStringContainsString('>Leyenda agregada</span>', $r->cuerpo);
        $this->assertStringContainsString('Texto: Ley N° 453: Tienes derecho a un comprobante.', $r->cuerpo);
        $this->assertStringContainsString('>Leyenda desactivada</span>', $r->cuerpo);
        $this->assertStringContainsString('Activa: sí → no', $r->cuerpo);
    }
}
