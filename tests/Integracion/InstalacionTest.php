<?php

declare(strict_types=1);

namespace Tests\Integracion;

use App\Core\DB;
use PDOException;
use Tests\CasoPrueba;

/**
 * Escenario: Instalación limpia
 *   Dado una PC con Docker y el repositorio recién clonado
 *   Cuando se ejecuta "docker compose up"
 *   Entonces el sistema responde con la base migrada y los datos de prueba cargados
 */
final class InstalacionTest extends CasoPrueba
{
    public function testLaBaseQuedaMigradaYConDatosDePrueba(): void
    {
        $r = $this->get('/salud');
        $datos = json_decode($r->cuerpo, true);

        $this->assertSame(200, $r->estado);
        $this->assertSame('ok', $datos['estado']);
        $this->assertGreaterThanOrEqual(1, $datos['migraciones']);
        $this->assertSame(5, $datos['usuarios']);
        $this->assertSame(25, $datos['productos']);
    }

    public function testLaBaseUsaLaHoraDeBolivia(): void
    {
        $this->assertSame('-04:00', DB::valor('SELECT @@session.time_zone'));
    }

    public function testCargaTurnosLeyendasYConfiguracion(): void
    {
        $this->assertSame(2, (int) DB::valor('SELECT COUNT(*) FROM turnos'));
        $this->assertGreaterThanOrEqual(8, (int) DB::valor('SELECT COUNT(*) FROM leyendas WHERE activo = 1'));
        $this->assertSame('1023456021', DB::valor('SELECT nit FROM negocio'));
        $this->assertSame(1, (int) DB::valor('SELECT COUNT(*) FROM parametros_fiscales'));
    }

    public function testLaBitacoraNoSeEditaNiSeBorra(): void
    {
        DB::insertar("INSERT INTO bitacora (accion) VALUES ('prueba')");

        try {
            DB::consulta("UPDATE bitacora SET accion = 'otra'");
            $this->fail('La bitácora no debería poder editarse');
        } catch (PDOException $e) {
            $this->assertStringContainsString('no se edita', $e->getMessage());
        }

        $this->expectException(PDOException::class);
        DB::consulta('DELETE FROM bitacora');
    }

    public function testRutaInexistenteDa404(): void
    {
        $this->assertSame(404, $this->get('/no-existe')->estado);
    }

    public function testMetodoNoPermitidoDa405(): void
    {
        $this->assertSame(405, $this->post('/salud')->estado);
    }

    public function testModuloSinConstruirMuestraProximamente(): void
    {
        $this->entrarComo('admin');
        $r = $this->get('/reportes');

        $this->assertSame(200, $r->estado);
        $this->assertStringContainsString('Próximamente', $r->cuerpo);
        $this->assertStringContainsString('HU-63', $r->cuerpo);
    }
}
