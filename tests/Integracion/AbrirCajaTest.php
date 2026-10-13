<?php

declare(strict_types=1);

namespace Tests\Integracion;

use App\Core\DB;
use App\Core\Reloj;
use App\Core\Sesion;
use App\Models\Bitacora;
use PDOException;
use Tests\CasoPrueba;

/** HU-20: abrir caja con turno, cajero y fondo inicial. */
final class AbrirCajaTest extends CasoPrueba
{
    private int $mediodia;
    private int $noche;

    protected function setUp(): void
    {
        parent::setUp();
        Reloj::fijar('2026-10-15 11:05:00');
        $this->mediodia = (int) DB::valor("SELECT id FROM turnos WHERE clave = 'mediodia'");
        $this->noche = (int) DB::valor("SELECT id FROM turnos WHERE clave = 'noche'");
    }

    private function idUsuario(string $usuario): int
    {
        return (int) DB::valor('SELECT id FROM usuarios WHERE usuario = ?', [$usuario]);
    }

    public function testSinCajaMuestraElFormularioConElTurnoDeLaHora(): void
    {
        $this->entrarComo('mariela', 'pin');

        $r = $this->get('/caja');

        $this->assertStringContainsString('Abrir caja', $r->cuerpo);
        $this->assertMatchesRegularExpression('/value="' . $this->mediodia . '" selected/', $r->cuerpo);
        $this->assertStringContainsString('Mariela Suárez Añez', $r->cuerpo);
    }

    public function testElCajeroAbreSuCajaYVaAlPuntoDeVenta(): void
    {
        $this->entrarComo('mariela', 'pin');

        $r = $this->post('/caja/abrir', ['turno_id' => $this->mediodia, 'fondo' => '300,00', 'cajero_id' => $this->idUsuario('jorge')]);

        $this->assertRedirige('/pos', $r);
        $c = DB::fila("SELECT * FROM cajas WHERE estado = 'abierta'");
        $this->assertSame($this->idUsuario('mariela'), (int) $c['cajero_id'], 'El cajero abre su propia caja aunque mande otro id');
        $this->assertSame('300.00', $c['fondo_inicial']);
        $this->assertSame('2026-10-15', $c['fecha']);
        $this->assertSame('2026-10-15 11:05:00', $c['abierta_en']);
        $this->assertCount(1, Bitacora::ultimos('caja.apertura'));
    }

    public function testElAdministradorAbreParaUnCajero(): void
    {
        $this->entrarComo('admin');

        $this->assertRedirige('/caja', $this->post('/caja/abrir', ['turno_id' => $this->mediodia, 'cajero_id' => $this->idUsuario('jorge'), 'fondo' => '250']));

        $c = DB::fila("SELECT * FROM cajas WHERE estado = 'abierta'");
        $this->assertSame($this->idUsuario('jorge'), (int) $c['cajero_id']);
        $this->assertSame($this->idUsuario('admin'), (int) $c['abierta_por']);
        $r = $this->get('/caja');
        $this->assertStringContainsString('Caja abierta', $r->cuerpo);
        $this->assertStringContainsString('250,00', $r->cuerpo);
    }

    /**
     * Escenario: Una sola caja
     *   Dado que la caja del turno de medio día está abierta
     *   Cuando otro cajero intenta abrir la del turno de noche
     *   Entonces el sistema lo impide y explica que primero se debe cerrar y arquear la anterior
     */
    public function testUnaSolaCajaAbierta(): void
    {
        $this->entrarComo('mariela', 'pin');
        $this->post('/caja/abrir', ['turno_id' => $this->mediodia, 'fondo' => '300']);

        Reloj::fijar('2026-10-15 18:02:00');
        $this->entrarComo('jorge', 'pin');
        $r = $this->post('/caja/abrir', ['turno_id' => $this->noche, 'fondo' => '300']);

        $this->assertRedirige('/caja', $r);
        $this->assertStringContainsString('Primero se debe cerrar y arquear esa caja', Sesion::get('_flash')['bloqueo']);
        $pagina = $this->get('/caja')->cuerpo;
        $this->assertStringContainsString('No se puede abrir otra caja', $pagina);
        $this->assertStringContainsString('turno de Medio día, de Mariela Suárez Añez', $pagina);
        $this->assertSame(1, (int) DB::valor('SELECT COUNT(*) FROM cajas'));
    }

    public function testLaBaseTambienImpideDosCajasAbiertas(): void
    {
        $this->entrarComo('mariela', 'pin');
        $this->post('/caja/abrir', ['turno_id' => $this->mediodia, 'fondo' => '300']);

        $this->expectException(PDOException::class);
        DB::consulta(
            "INSERT INTO cajas (turno_id, fecha, cajero_id, abierta_por, fondo_inicial, abierta_en) VALUES (?, '2026-10-15', ?, ?, 0, NOW())",
            [$this->noche, $this->idUsuario('jorge'), $this->idUsuario('jorge')],
        );
    }

    public function testUnaCajaPorTurnoYDia(): void
    {
        $this->entrarComo('mariela', 'pin');
        $this->post('/caja/abrir', ['turno_id' => $this->mediodia, 'fondo' => '300']);
        DB::consulta("UPDATE cajas SET estado = 'cerrada'");

        $r = $this->post('/caja/abrir', ['turno_id' => $this->mediodia, 'fondo' => '300']);

        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString('ya se abrió y se cerró', $r->cuerpo);
        // Cerrada la de medio día, la de noche sí abre
        $this->assertRedirige('/pos', $this->post('/caja/abrir', ['turno_id' => $this->noche, 'fondo' => '300']));
    }

    public function testFondoInvalido(): void
    {
        $this->entrarComo('mariela', 'pin');

        $r = $this->post('/caja/abrir', ['turno_id' => $this->mediodia, 'fondo' => 'mucho']);

        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString('Escribe el fondo inicial', $r->cuerpo);
        $this->assertSame(0, (int) DB::valor('SELECT COUNT(*) FROM cajas'));
    }

    public function testNoSeAbreParaUnUsuarioQueNoEsCajero(): void
    {
        $this->entrarComo('admin');

        $r = $this->post('/caja/abrir', ['turno_id' => $this->mediodia, 'cajero_id' => $this->idUsuario('luis'), 'fondo' => '300']);

        $this->assertStringContainsString('Elige un cajero activo.', $r->cuerpo);
    }

    public function testElMeseroNoAbreCaja(): void
    {
        $this->entrarComo('luis');

        $this->assertSame(403, $this->post('/caja/abrir', ['turno_id' => $this->mediodia, 'fondo' => '300'])->estado);
    }
}
