<?php

declare(strict_types=1);

namespace Tests\Integracion;

use App\Core\DB;
use App\Core\Reloj;
use App\Models\Bitacora;
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

    /** Guarda también el nombre de cada turno (el campo de la ficha). */
    private function enviarConNombres(string $nombre1, string $nombre2, string $ini1, string $fin1, string $ini2, string $fin2)
    {
        return $this->post('/config/turnos', ['turnos' => [
            $this->mediodia => ['nombre' => $nombre1, 'inicio' => $ini1, 'fin' => $fin1],
            $this->noche => ['nombre' => $nombre2, 'inicio' => $ini2, 'fin' => $fin2],
        ]]);
    }

    /**
     * Escenario 1: Turnos iniciales
     *   Dado el sistema recién instalado
     *   Cuando reviso los turnos
     *   Entonces existen Medio día (11:00 a 16:00) y Noche (18:00 a 24:00)
     */
    public function testLosTurnosDeFabrica(): void
    {
        $t = DB::filas('SELECT clave, hora_inicio, hora_fin FROM turnos ORDER BY orden');

        $this->assertSame(['mediodia', '11:00:00', '16:00:00'], array_values($t[0]));
        $this->assertSame(['noche', '18:00:00', '24:00:00'], array_values($t[1]));
        $this->assertStringContainsString('Medio día', $this->get('/config')->cuerpo);
        $this->assertStringContainsString('Noche', $this->get('/config')->cuerpo);
    }

    public function testLaPantallaMuestraMedianocheComo2359(): void
    {
        $this->assertStringContainsString('value="23:59"', $this->get('/config')->cuerpo);
    }

    /**
     * Escenario 2: Cambio de horario
     *   Dado modifico la hora de inicio de un turno
     *   Cuando guardo
     *   Entonces el turno usa el horario nuevo
     */
    public function testCambiaElHorario(): void
    {
        $this->assertRedirige('/config#turnos', $this->enviar('11:30', '15:30', '18:00', '23:59'));

        $this->assertSame('11:30:00', DB::valor('SELECT hora_inicio FROM turnos WHERE id = ?', [$this->mediodia]));
        $this->assertSame('24:00:00', DB::valor('SELECT hora_fin FROM turnos WHERE id = ?', [$this->noche]));
        $this->assertStringContainsString('11:30 a 15:30', Bitacora::ultimos('config.turnos')[0]['detalle']);
    }

    /** El horario guardado es el que queda escrito y el que se muestra y se usa al abrir caja. */
    public function testElHorarioNuevoEsElQueSeUsa(): void
    {
        $this->enviar('11:30', '15:30', '18:00', '23:59');

        $this->assertSame('11:30 a 15:30', Turno::horario(Turno::buscar($this->mediodia)));
        $this->assertStringContainsString('value="11:30"', $this->get('/config')->cuerpo);

        $this->entrarComo('mariela', 'pin');
        $caja = $this->get('/caja')->cuerpo;
        $this->assertStringContainsString('Medio día · 11:30 a 15:30', $caja);
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

    /** Un error no borra los horarios ya escritos: la pantalla los vuelve a mostrar. */
    public function testAlFallarSeConservanLosHorariosEscritos(): void
    {
        $r = $this->enviar('11:30', '15:30', '19:00', '18:00');

        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString('value="11:30"', $r->cuerpo);
        $this->assertStringContainsString('value="15:30"', $r->cuerpo);
    }

    public function testAgregarTurnoNuevo(): void
    {
        $r = $this->post('/config/turnos', [
            'agregar' => '1',
            'nuevo_nombre' => 'Madrugada',
            'nuevo_inicio' => '06:00',
            'nuevo_fin' => '09:00',
            'nuevo_orden' => '3',
        ]);

        $this->assertRedirige('/config#turnos', $r);
        $this->assertSame('06:00:00', DB::valor('SELECT hora_inicio FROM turnos WHERE nombre = ?', ['Madrugada']));
        $this->assertSame('09:00:00', DB::valor('SELECT hora_fin FROM turnos WHERE nombre = ?', ['Madrugada']));
        $this->assertCount(3, DB::filas('SELECT id FROM turnos ORDER BY orden'));
        $this->assertCount(1, Bitacora::ultimos('config.turnos.agregar'));
    }

    /** La clave la arma el sistema con el nombre: el administrador no la escribe. */
    public function testLaClaveSeArmaConElNombre(): void
    {
        $this->post('/config/turnos', [
            'agregar' => '1',
            'nuevo_nombre' => 'Mañana',
            'nuevo_inicio' => '06:00',
            'nuevo_fin' => '09:00',
            'nuevo_orden' => '3',
        ]);

        $this->assertSame('manana', DB::valor('SELECT clave FROM turnos WHERE nombre = ?', ['Mañana']));
    }

    /** Si la clave ya existe, el sistema le pone un número y el turno entra igual. */
    public function testLaClaveNoSeRepite(): void
    {
        $this->post('/config/turnos', [
            'agregar' => '1',
            'nuevo_nombre' => 'Turno A',
            'nuevo_inicio' => '06:00',
            'nuevo_fin' => '09:00',
            'nuevo_orden' => '3',
        ]);

        $this->post('/config/turnos', [
            'agregar' => '1',
            'nuevo_nombre' => 'Turno-A',
            'nuevo_inicio' => '09:30',
            'nuevo_fin' => '10:00',
            'nuevo_orden' => '4',
        ]);

        $this->assertSame('turno_a', DB::valor('SELECT clave FROM turnos WHERE nombre = ?', ['Turno A']));
        $this->assertSame('turno_a2', DB::valor('SELECT clave FROM turnos WHERE nombre = ?', ['Turno-A']));
    }

    /** El turno que se agrega no puede cruzar la medianoche: la base lo rechaza. */
    public function testNoAgregarTurnoQueCruzaLaMedianoche(): void
    {
        $r = $this->post('/config/turnos', [
            'agregar' => '1',
            'nuevo_nombre' => 'Madrugada',
            'nuevo_inicio' => '22:00',
            'nuevo_fin' => '06:00',
            'nuevo_orden' => '3',
        ]);

        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString('Debe terminar después de empezar.', $r->cuerpo);
        $this->assertCount(2, DB::filas('SELECT id FROM turnos'));
    }

    public function testNoAgregarTurnoConNombreDuplicado(): void
    {
        $r = $this->post('/config/turnos', [
            'agregar' => '1',
            'nuevo_nombre' => 'Noche',
            'nuevo_inicio' => '06:00',
            'nuevo_fin' => '09:00',
            'nuevo_orden' => '3',
        ]);

        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString('ya existe', $r->cuerpo);
        $this->assertCount(2, DB::filas('SELECT id FROM turnos'));
    }

    public function testNoAgregarTurnoQueSeCruzaConOtro(): void
    {
        $r = $this->post('/config/turnos', [
            'agregar' => '1',
            'nuevo_nombre' => 'Tarde',
            'nuevo_inicio' => '17:00',
            'nuevo_fin' => '19:00',
            'nuevo_orden' => '3',
        ]);

        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString('Se cruza con el turno de Noche, que va de 18:00 a 23:59.', $r->cuerpo);
    }

    /** Al fallar el alta, lo escrito se conserva para reintentar. */
    public function testAlFallarElAltaSeConservaLoEscrito(): void
    {
        $r = $this->post('/config/turnos', [
            'agregar' => '1',
            'nuevo_nombre' => 'Madrugada',
            'nuevo_inicio' => '06:00',
            'nuevo_fin' => '09:00',
            'nuevo_orden' => 'x',
        ]);

        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString('value="Madrugada"', $r->cuerpo);
        $this->assertStringContainsString('value="06:00"', $r->cuerpo);
    }

    public function testEliminarTurnoSinCajas(): void
    {
        $r = $this->post('/config/turnos', ['eliminar' => $this->noche]);

        $this->assertRedirige('/config#turnos', $r);
        $this->assertNull(Turno::buscar($this->noche));
        $this->assertCount(1, Bitacora::ultimos('config.turnos.eliminar'));
    }

    /** En la tabla de la bitácora el turno sale con su nombre, aunque ya se haya borrado. */
    public function testLaBitacoraMuestraElNombreDelTurnoEliminado(): void
    {
        $this->post('/config/turnos', ['eliminar' => $this->noche]);

        $r = $this->get('/bitacora');

        $this->assertStringContainsString('Turno: Noche', $r->cuerpo);
        $this->assertStringNotContainsString('Turno: #' . $this->noche, $r->cuerpo);
    }

    /** Lo mismo al cambiar el horario: la fila guarda el nombre del turno. */
    public function testLaBitacoraMuestraElNombreDelTurnoEditado(): void
    {
        $this->enviar('11:30', '15:30', '18:00', '23:59');

        $r = $this->get('/bitacora');

        $this->assertStringContainsString('Turno: Medio día', $r->cuerpo);
    }

    public function testNoEliminarTurnoQueNoExiste(): void
    {
        $r = $this->post('/config/turnos', ['eliminar' => 999]);

        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString('Elige el turno que quieres eliminar.', $r->cuerpo);
    }

    public function testNoEliminarUltimoTurno(): void
    {
        $this->post('/config/turnos', ['eliminar' => $this->noche]);

        $r = $this->post('/config/turnos', ['eliminar' => $this->mediodia]);

        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString('único turno', $r->cuerpo);
    }

    public function testNoEliminarTurnoConCajasHoy(): void
    {
        DB::consulta(
            "INSERT INTO cajas (turno_id, fecha, cajero_id, abierta_por, fondo_inicial, abierta_en) VALUES (?, ?, 1, 1, 100, NOW())",
            [$this->mediodia, Reloj::ahora()->format('Y-m-d')],
        );

        $r = $this->post('/config/turnos', ['eliminar' => $this->mediodia]);

        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString('ya tiene cajas hoy', $r->cuerpo);
    }

    /** Un turno con cajas de otro día tampoco se elimina: la llave foránea lo impide. */
    public function testNoEliminarTurnoConCajasDeOtroDia(): void
    {
        DB::consulta(
            "INSERT INTO cajas (turno_id, fecha, cajero_id, abierta_por, fondo_inicial, abierta_en) VALUES (?, '2026-10-01', 1, 1, 100, NOW())",
            [$this->mediodia],
        );

        $r = $this->post('/config/turnos', ['eliminar' => $this->mediodia]);

        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString('cajas', $r->cuerpo);
        $this->assertNotNull(Turno::buscar($this->mediodia));
    }

    /** Un turno en curso cambia como los demás: su horario nuevo también se guarda. */
    public function testUnTurnoEnCursoCambiaComoLosDemas(): void
    {
        Reloj::fijar('2026-10-15 12:00:00');
        $this->entrarComo('admin');

        $r = $this->enviar('10:00', '15:00', '19:00', '23:59');

        $this->assertRedirige('/config#turnos', $r);
        $this->assertSame('10:00:00', DB::valor('SELECT hora_inicio FROM turnos WHERE id = ?', [$this->mediodia]));
        $this->assertSame('19:00:00', DB::valor('SELECT hora_inicio FROM turnos WHERE id = ?', [$this->noche]));
    }

    /**
     * Escenario 3: Apertura por turno
     *   Dado un Cajero abre caja
     *   Cuando elige el turno
     *   Entonces solo puede elegir los turnos configurados
     */
    public function testLaCajaOfreceSoloLosTurnosConfigurados(): void
    {
        $this->post('/config/turnos', [
            'agregar' => '1',
            'nuevo_nombre' => 'Madrugada',
            'nuevo_inicio' => '06:00',
            'nuevo_fin' => '09:00',
            'nuevo_orden' => '3',
        ]);

        $this->entrarComo('mariela', 'pin');
        $caja = $this->get('/caja')->cuerpo;

        $this->assertStringContainsString('Medio día · 11:00 a 16:00', $caja);
        $this->assertStringContainsString('Noche · 18:00 a 23:59', $caja);
        $this->assertStringContainsString('Madrugada · 06:00 a 09:00', $caja);
    }

    /** Un turno que no está configurado no se puede abrir. */
    public function testNoSeAbreCajaConUnTurnoQueNoExiste(): void
    {
        $this->entrarComo('mariela', 'pin');

        $r = $this->post('/caja/abrir', ['turno_id' => 999, 'fondo' => '300,00']);

        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString('Elige un turno de la lista.', $r->cuerpo);
    }

    /** La bitácora nombra el turno afectado: «Turno: Medio día». */
    public function testLaBitacoraNombraElTurno(): void
    {
        $this->enviar('11:30', '15:30', '18:00', '23:59');

        $registro = Bitacora::ultimos('config.turnos')[0];
        $this->assertSame('turnos', $registro['entidad']);
        $this->assertSame((string) $this->mediodia, $registro['entidad_id']);
    }

    /** Agregar turno se hace desde un modal: nombre, horario y orden del sistema. */
    public function testElModalDeAgregarTurno(): void
    {
        $pantalla = $this->get('/config')->cuerpo;

        $this->assertStringContainsString('id="modal-turno"', $pantalla);
        $this->assertStringContainsString('data-abrir-modal="modal-turno"', $pantalla);
        $this->assertStringContainsString('name="nuevo_nombre"', $pantalla);
        $this->assertStringContainsString('name="nuevo_inicio"', $pantalla);
        $this->assertStringContainsString('name="nuevo_fin"', $pantalla);
        $this->assertStringNotContainsString('name="nueva_clave"', $pantalla);
    }

    /** El modal ya no trae atajos de mañana, día, tarde y noche: el nombre se escribe. */
    public function testElModalDeAgregarNoTieneAtajos(): void
    {
        $pantalla = $this->get('/config')->cuerpo;

        $this->assertStringNotContainsString('data-turno-nombre="Mañana"', $pantalla);
        $this->assertStringNotContainsString('data-turno-nombre="Día"', $pantalla);
        $this->assertStringNotContainsString('data-turno-nombre="Tarde"', $pantalla);
        $this->assertStringNotContainsString('data-turno-nombre="Noche"', $pantalla);
    }

    /** El orden se ve pero no se edita: lo pone el sistema. */
    public function testElOrdenDelTurnoNuevoNoSeEdita(): void
    {
        $pantalla = $this->get('/config')->cuerpo;

        $this->assertStringContainsString('name="nuevo_orden"', $pantalla);
        $this->assertMatchesRegularExpression('/id="nuevo_orden"[^>]*readonly/', $pantalla);
    }

    /** La pantalla resume el día: cuántos turnos hay y de qué hora a qué hora. */
    public function testLaPantallaResumeLosTurnos(): void
    {
        $pantalla = $this->get('/config')->cuerpo;

        $this->assertStringContainsString('data-resumen-turnos', $pantalla);
        $this->assertStringContainsString('2 turnos · de 11:00 a 23:59', $pantalla);
    }

    /** La ficha escribe el horario como en la pantalla de abrir caja: «Noche · 18:00 a 23:59». */
    public function testLaFichaMuestraElHorarioComoLaCaja(): void
    {
        $pantalla = $this->get('/config')->cuerpo;

        $this->assertStringContainsString('value="Medio día"', $pantalla);
        $this->assertStringContainsString('data-horario>11:00 a 16:00<', $pantalla);
        $this->assertStringContainsString('data-horario>18:00 a 23:59<', $pantalla);

        $this->entrarComo('mariela', 'pin');
        $caja = $this->get('/caja')->cuerpo;
        $this->assertStringContainsString('Medio día · 11:00 a 16:00', $caja);
        $this->assertStringContainsString('Noche · 18:00 a 23:59', $caja);
    }

    /** La medianoche se muestra como la marca el administrador: 23:59, no 24:00. */
    public function testLaMedianocheSeMuestraComo2359(): void
    {
        $pantalla = $this->get('/config')->cuerpo;

        $this->assertStringContainsString('value="23:59"', $pantalla);
        $this->assertStringNotContainsString('24:00', $pantalla);
    }

    /** La pantalla no marca el turno en curso: cualquier turno se puede editar. */
    public function testElTurnoEnCursoSePuedeEditar(): void
    {
        Reloj::fijar('2026-10-15 12:00:00');
        $this->entrarComo('admin');

        $pantalla = $this->get('/config')->cuerpo;

        $this->assertStringNotContainsString('En curso', $pantalla);
        $this->assertDoesNotMatchRegularExpression('/id="t' . $this->mediodia . '-ini"[^>]*readonly/', $pantalla);

        $this->assertRedirige('/config#turnos', $this->enviar('12:00', '16:00', '18:00', '23:59'));
        $this->assertSame('12:00:00', DB::valor('SELECT hora_inicio FROM turnos WHERE id = ?', [$this->mediodia]));
    }

    /** El nombre de cada turno se escribe en su ficha. */
    public function testElNombreDelTurnoSeEscribeEnSuFicha(): void
    {
        $pantalla = $this->get('/config')->cuerpo;

        $this->assertStringContainsString('name="turnos[' . $this->mediodia . '][nombre]"', $pantalla);
        $this->assertStringContainsString('value="Medio día"', $pantalla);
    }

    /** El nombre del turno se puede cambiar y queda en la bitácora. */
    public function testCambiaElNombreDelTurno(): void
    {
        $r = $this->enviarConNombres('Mediodía', 'Noche', '11:00', '16:00', '18:00', '23:59');

        $this->assertRedirige('/config#turnos', $r);
        $this->assertSame('Mediodía', DB::valor('SELECT nombre FROM turnos WHERE id = ?', [$this->mediodia]));
        $this->assertSame('Mediodía', Turno::buscar($this->mediodia)['nombre']);
        $this->assertStringContainsString('value="Mediodía"', $this->get('/config')->cuerpo);

        $fila = Bitacora::ultimos('config.turnos.nombre')[0];
        $this->assertStringContainsString('Medio día', $fila['detalle']);
        $this->assertStringContainsString('Mediodía', $fila['detalle']);
    }

    /** Un nombre vacío no se guarda. */
    public function testNoDejaElNombreDelTurnoVacio(): void
    {
        $r = $this->enviarConNombres('', 'Noche', '11:00', '16:00', '18:00', '23:59');

        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString('El nombre tiene de 1 a 30 caracteres.', $r->cuerpo);
        $this->assertSame('Medio día', DB::valor('SELECT nombre FROM turnos WHERE id = ?', [$this->mediodia]));
    }

    /** Dos turnos no pueden llamarse igual. */
    public function testNoRepiteElNombreDeOtroTurno(): void
    {
        $r = $this->enviarConNombres('Noche', 'Noche', '11:00', '16:00', '18:00', '23:59');

        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString('Ese nombre de turno ya existe.', $r->cuerpo);
        $this->assertSame('Medio día', DB::valor('SELECT nombre FROM turnos WHERE id = ?', [$this->mediodia]));
    }

    /** El turno en curso también se puede eliminar. */
    public function testElTurnoEnCursoSePuedeEliminar(): void
    {
        Reloj::fijar('2026-10-15 12:00:00');
        $this->entrarComo('admin');

        $r = $this->post('/config/turnos', ['eliminar' => $this->mediodia]);

        $this->assertRedirige('/config#turnos', $r);
        $this->assertNull(Turno::buscar($this->mediodia));
    }

    /** Cuando el sistema no deja eliminar, el motivo sale en un aviso visible. */
    public function testElMotivoDeNoPoderEliminarSeVeEnUnAviso(): void
    {
        $this->post('/config/turnos', ['eliminar' => $this->noche]);

        $r = $this->post('/config/turnos', ['eliminar' => $this->mediodia]);

        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString('alerta--peligro', $r->cuerpo);
        $this->assertStringContainsString('No se puede eliminar el único turno que queda.', $r->cuerpo);
    }

    /** Cada turno se borra con su propio botón, sin marcar y sin «Eliminar marcados». */
    public function testCadaTurnoTieneSuBotonDeEliminar(): void
    {
        $pantalla = $this->get('/config')->cuerpo;

        $this->assertStringContainsString('data-eliminar-turno="' . $this->mediodia . '"', $pantalla);
        $this->assertStringContainsString('id="modal-eliminar-turno"', $pantalla);
        $this->assertStringNotContainsString('Eliminar marcados', $pantalla);
        $this->assertStringNotContainsString('turnos_eliminar', $pantalla);
    }
}
