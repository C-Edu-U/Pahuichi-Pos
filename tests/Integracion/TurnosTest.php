<?php

declare(strict_types=1);

namespace Tests\Integracion;

use App\Core\DB;
use App\Core\Reloj;
use App\Models\Bitacora;
use App\Models\Caja;
use App\Models\Turno;
use Tests\CasoPrueba;

/** HU-11: turnos configurables. */
final class TurnosTest extends CasoPrueba
{
    private int $mediodia;
    private int $noche;

    protected function setUp(): void
    {
        parent::setUp();
        Reloj::fijar('2026-10-15 10:00:00');
        $this->entrarComo('admin');
        $this->mediodia = (int) DB::valor("SELECT id FROM turnos WHERE clave = 'mediodia'");
        $this->noche = (int) DB::valor("SELECT id FROM turnos WHERE clave = 'noche'");
    }

    private function enviar(string $ini1, string $fin1, string $ini2, string $fin2)
    {
        return $this->post('/config/turnos', ['turnos' => [
            $this->mediodia => ['inicio' => $ini1, 'fin' => $fin1],
            $this->noche => ['inicio' => $ini2, 'fin' => $fin2],
        ]]);
    }

    public function testLosTurnosDeFabrica(): void
    {
        $t = DB::filas('SELECT clave, hora_inicio, hora_fin FROM turnos ORDER BY orden');

        $this->assertSame(['mediodia', '11:00:00', '16:00:00'], array_values($t[0]));
        $this->assertSame(['noche', '18:00:00', '24:00:00'], array_values($t[1]));
    }

    public function testLaPantallaMuestraMedianocheComo2359(): void
    {
        $this->assertStringContainsString('value="23:59"', $this->get('/config')->cuerpo);
    }

    public function testCambiaElHorario(): void
    {
        $this->assertRedirige('/config#turnos', $this->enviar('11:30', '15:30', '18:00', '23:59'));

        $this->assertSame('11:30:00', DB::valor('SELECT hora_inicio FROM turnos WHERE id = ?', [$this->mediodia]));
        $this->assertSame('24:00:00', DB::valor('SELECT hora_fin FROM turnos WHERE id = ?', [$this->noche]));
        $this->assertStringContainsString('11:30 a 15:30', Bitacora::ultimos('config.turnos')[0]['detalle']);
    }

    public function testElFinDebeSerDespuesDelInicio(): void
    {
        $r = $this->enviar('16:00', '11:00', '18:00', '23:59');

        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString('Debe terminar después de empezar.', $r->cuerpo);
        $this->assertSame('11:00:00', DB::valor('SELECT hora_inicio FROM turnos WHERE id = ?', [$this->mediodia]));
    }

    public function testLosTurnosNoSeCruzan(): void
    {
        $r = $this->enviar('11:00', '19:00', '18:00', '23:59');

        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString('Se cruza con el turno de Medio día, que termina a las 19:00.', $r->cuerpo);
    }

    public function testHoraInvalida(): void
    {
        $this->assertStringContainsString('Elige la hora de inicio.', $this->enviar('', '16:00', '18:00', '23:59')->cuerpo);
    }

    public function testAgregarTurnoNuevo(): void
    {
        $r = $this->post('/config/turnos', [
            'agregar' => '1',
            'nueva_clave' => 'madrugada',
            'nuevo_nombre' => 'Madrugada',
            'nuevo_inicio' => '06:00',
            'nuevo_fin' => '09:00',
            'nuevo_orden' => '3',
        ]);

        $this->assertRedirige('/config#turnos', $r);
        $this->assertSame('06:00:00', DB::valor('SELECT hora_inicio FROM turnos WHERE clave = ?', ['madrugada']));
        $this->assertSame('09:00:00', DB::valor('SELECT hora_fin FROM turnos WHERE clave = ?', ['madrugada']));
        $this->assertCount(3, DB::filas('SELECT id FROM turnos ORDER BY orden'));
        $this->assertCount(1, Bitacora::ultimos('config.turnos.agregar'));
    }

    public function testNoAgregarTurnoConClaveDuplicada(): void
    {
        $r = $this->post('/config/turnos', [
            'agregar' => '1',
            'nueva_clave' => 'mediodia',
            'nuevo_nombre' => 'Duplicado',
            'nuevo_inicio' => '06:00',
            'nuevo_fin' => '09:00',
            'nuevo_orden' => '3',
        ]);

        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString('ya existe', $r->cuerpo);
    }

    public function testNoAgregarTurnoConFinAntesDeInicio(): void
    {
        $r = $this->post('/config/turnos', [
            'agregar' => '1',
            'nueva_clave' => 'madrugada',
            'nuevo_nombre' => 'Madrugada',
            'nuevo_inicio' => '06:00',
            'nuevo_fin' => '02:00',
            'nuevo_orden' => '3',
        ]);

        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString('Debe terminar después de empezar.', $r->cuerpo);
    }

    public function testEliminarTurnoSinCajas(): void
    {
        $r = $this->post('/config/turnos', [
            'eliminar' => '1',
            'turnos_eliminar' => [$this->noche],
        ]);

        $this->assertRedirige('/config#turnos', $r);
        $this->assertNull(Turno::buscar($this->noche));
        $this->assertCount(1, Bitacora::ultimos('config.turnos.eliminar'));
    }

    public function testNoEliminarUltimoTurno(): void
    {
        $this->post('/config/turnos', ['eliminar' => '1', 'turnos_eliminar' => [$this->noche]]);

        $r = $this->post('/config/turnos', ['eliminar' => '1', 'turnos_eliminar' => [$this->mediodia]]);

        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString('único turno', $r->cuerpo);
    }

    public function testNoEliminarTurnoConCajas(): void
    {
        // Una caja de otro día también lo impide: sus reportes nombran el turno
        DB::consulta("INSERT INTO cajas (turno_id, fecha, cajero_id, abierta_por, fondo_inicial, abierta_en, estado) VALUES (?, '2026-10-01', 1, 1, 100, NOW(), 'cerrada')", [$this->mediodia]);

        $r = $this->post('/config/turnos', ['eliminar' => '1', 'turnos_eliminar' => [$this->mediodia]]);

        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString('ya tiene cajas y no se puede eliminar', $r->cuerpo);
        $this->assertNotNull(Turno::buscar($this->mediodia));
    }

    /**
     * Escenario: Cambio de horario con un turno en curso
     *   Dado son las 12:00 y el turno de medio día está en curso
     *   Cuando el administrador cambia el horario de la noche (o el del propio medio día) y guarda
     *   Entonces los cambios se guardan
     */
    public function testSeEditaConUnTurnoEnCurso(): void
    {
        Reloj::fijar('2026-10-15 12:00:00');
        $this->entrarComo('admin');

        $this->assertRedirige('/config#turnos', $this->enviar('11:00', '16:00', '17:00', '23:59'));
        $this->assertSame('17:00:00', DB::valor('SELECT hora_inicio FROM turnos WHERE id = ?', [$this->noche]));

        $this->assertRedirige('/config#turnos', $this->enviar('10:30', '16:00', '17:00', '23:59'));
        $this->assertSame('10:30:00', DB::valor('SELECT hora_inicio FROM turnos WHERE id = ?', [$this->mediodia]));
        $this->assertStringNotContainsString('está en curso', $this->get('/config')->cuerpo);
    }

    public function testSeEliminaUnTurnoEnCursoSinCajas(): void
    {
        Reloj::fijar('2026-10-15 12:00:00');
        $this->entrarComo('admin');

        $this->assertRedirige('/config#turnos', $this->post('/config/turnos', ['eliminar' => '1', 'turnos_eliminar' => [$this->mediodia]]));
        $this->assertNull(Turno::buscar($this->mediodia));
    }

    public function testConUnErrorLaPantallaConservaLoEscrito(): void
    {
        $r = $this->enviar('11:00', '16:00', '20:00', '19:00');

        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString('value="11:00"', $r->cuerpo);
        $this->assertStringContainsString('value="16:00"', $r->cuerpo);
        $this->assertStringContainsString('value="20:00"', $r->cuerpo);
        $this->assertStringContainsString('value="19:00"', $r->cuerpo);
        $this->assertStringNotContainsString('Elige la hora de inicio.', $r->cuerpo);
    }

    /**
     * Escenario: La caja conserva su horario
     *   Dado la caja de medio día se abrió con el horario 11:00 a 16:00
     *   Cuando el administrador cambia el horario de medio día a 10:30 a 15:30
     *   Entonces esa caja sigue mostrando 11:00 a 16:00 y la próxima caja usa el horario nuevo
     */
    public function testLaCajaConservaElHorarioConElQueAbrio(): void
    {
        Reloj::fijar('2026-10-15 11:30:00');
        $this->entrarComo('mariela', 'pin');
        $this->post('/caja/abrir', ['turno_id' => $this->mediodia, 'fondo' => '200']);
        $caja = (int) DB::valor("SELECT id FROM cajas WHERE estado = 'abierta'");

        $this->entrarComo('admin');
        $this->assertRedirige('/config#turnos', $this->enviar('10:30', '15:30', '18:00', '23:59'));

        $this->assertSame('11:00 a 16:00', Turno::horario((array) Caja::buscar($caja)));
        $this->assertStringContainsString('11:00 a 16:00', $this->get('/caja')->cuerpo);

        DB::consulta("UPDATE cajas SET estado = 'cerrada' WHERE id = ?", [$caja]);
        Reloj::fijar('2026-10-16 11:00:00');
        $this->entrarComo('mariela', 'pin');
        $this->post('/caja/abrir', ['turno_id' => $this->mediodia, 'fondo' => '200']);
        $nueva = (int) DB::valor("SELECT id FROM cajas WHERE estado = 'abierta'");
        $this->assertSame('10:30 a 15:30', Turno::horario((array) Caja::buscar($nueva)));
    }

    public function testLaMedianocheSeVe2359EnTodasLasPantallas(): void
    {
        $this->assertSame('18:00 a 23:59', Turno::horario(['hora_inicio' => '18:00:00', 'hora_fin' => '24:00:00']));

        Reloj::fijar('2026-10-15 18:30:00');
        $this->entrarComo('mariela', 'pin');
        $html = $this->get('/caja')->cuerpo;

        $this->assertStringContainsString('Noche · 18:00 a 23:59', $html);
        $this->assertStringNotContainsString('24:00', $html);
    }
}
