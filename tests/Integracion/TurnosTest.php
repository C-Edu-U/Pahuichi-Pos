<?php

declare(strict_types=1);

namespace Tests\Integracion;

use App\Core\DB;
use App\Models\Bitacora;
use Tests\CasoPrueba;

/** HU-11: turnos configurables. */
final class TurnosTest extends CasoPrueba
{
    private int $mediodia;
    private int $noche;

    protected function setUp(): void
    {
        parent::setUp();
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
}
