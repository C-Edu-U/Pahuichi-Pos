<?php

declare(strict_types=1);

namespace Tests\Integracion;

use App\Core\App;
use App\Core\DB;
use App\Core\Peticion;
use App\Core\Reloj;
use App\Core\Sesion;
use App\Models\Bitacora;
use Tests\CasoPrueba;

/** HU-02: inicio de sesión con contraseña y PIN, bloqueo e inactividad. */
final class InicioSesionTest extends CasoPrueba
{
    /**
     * Escenario: Login del cajero con PIN
     *   Dado un cajero activo con PIN 4821
     *   Cuando ingresa el PIN en la PC de caja
     *   Entonces entra directo al punto de venta
     */
    public function testCajeroEntraConPinDirectoAlPuntoDeVenta(): void
    {
        $r = $this->post('/login', ['modo' => 'pin', 'pin' => '4821']);

        $this->assertRedirige('/pos', $r);
        $this->assertSame('mariela', DB::valor('SELECT usuario FROM usuarios WHERE id = ?', [Sesion::get('usuario_id')]));
        $this->assertNotNull(DB::valor("SELECT ultimo_ingreso FROM usuarios WHERE usuario = 'mariela'"));
        $this->assertCount(1, Bitacora::ultimos('sesion.inicio'));
    }

    public function testAdministradorEntraConContrasenaAlTablero(): void
    {
        $this->assertRedirige('/tablero', $this->post('/login', ['modo' => 'clave', 'usuario' => 'admin', 'clave' => 'Pahuichi2026']));
    }

    public function testMeseroEntraASusPedidos(): void
    {
        $this->assertRedirige('/mesero', $this->post('/login', ['modo' => 'clave', 'usuario' => 'LUIS', 'clave' => 'Pahuichi2026']));
    }

    public function testElPinDelAdministradorNoAbreLaCaja(): void
    {
        $r = $this->post('/login', ['modo' => 'pin', 'pin' => '1234']);

        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString('PIN incorrecto', $r->cuerpo);
    }

    public function testContrasenaErradaAvisaCuantosIntentosQuedan(): void
    {
        $r = $this->post('/login', ['modo' => 'clave', 'usuario' => 'admin', 'clave' => 'otra']);

        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString('Te quedan 4 intentos', $r->cuerpo);
        $this->assertNull(Sesion::get('usuario_id'));
    }

    /**
     * Escenario: Bloqueo por intentos
     *   Dado un usuario activo
     *   Cuando falla la contraseña 5 veces seguidas
     *   Entonces su cuenta queda bloqueada 15 minutos y el intento queda en la bitácora
     */
    public function testCincoFallosBloqueanLaCuenta15Minutos(): void
    {
        Reloj::fijar('2026-10-15 12:00:00');
        for ($i = 0; $i < 5; $i++) {
            $r = $this->post('/login', ['modo' => 'clave', 'usuario' => 'luis', 'clave' => 'mala']);
        }

        $this->assertStringContainsString('Acceso bloqueado por 15 minutos', $r->cuerpo);
        $this->assertStringContainsString('12:15', $r->cuerpo);
        $this->assertSame('2026-10-15 12:15:00', DB::valor("SELECT bloqueado_hasta FROM usuarios WHERE usuario = 'luis'"));
        $bloqueo = Bitacora::ultimos('sesion.bloqueo');
        $this->assertCount(1, $bloqueo);
        $this->assertSame((int) DB::valor("SELECT id FROM usuarios WHERE usuario = 'luis'"), (int) $bloqueo[0]['usuario_id']);

        // Con la contraseña correcta tampoco entra mientras dure el bloqueo
        $r = $this->post('/login', ['modo' => 'clave', 'usuario' => 'luis', 'clave' => 'Pahuichi2026']);
        $this->assertStringContainsString('Acceso bloqueado', $r->cuerpo);

        // Pasados los 15 minutos, entra
        Reloj::fijar('2026-10-15 12:15:01');
        $this->assertRedirige('/mesero', $this->post('/login', ['modo' => 'clave', 'usuario' => 'luis', 'clave' => 'Pahuichi2026']));
    }

    public function testCincoPinesErradosBloqueanEseEquipo(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $r = $this->post('/login', ['modo' => 'pin', 'pin' => '0000'], '10.0.0.7');
        }
        $this->assertStringContainsString('Acceso bloqueado', $r->cuerpo);

        // Bloqueado aunque ahora use un PIN válido
        $this->assertStringContainsString('Acceso bloqueado', $this->post('/login', ['modo' => 'pin', 'pin' => '4821'], '10.0.0.7')->cuerpo);
        // Otro equipo puede entrar
        $this->assertRedirige('/pos', $this->post('/login', ['modo' => 'pin', 'pin' => '4821'], '10.0.0.8'));
        $this->assertCount(1, Bitacora::ultimos('sesion.bloqueo'));
    }

    public function testUsuarioInexistenteRespondeIgualQueClaveErrada(): void
    {
        $r = $this->post('/login', ['modo' => 'clave', 'usuario' => 'nadie', 'clave' => 'x']);

        $this->assertStringContainsString('Usuario o contraseña incorrectos', $r->cuerpo);
        $this->assertStringContainsString('Te quedan 4 intentos', $r->cuerpo);
    }

    public function testCocinaNoEntraAlPanel(): void
    {
        $r = $this->post('/login', ['modo' => 'clave', 'usuario' => 'teresa', 'clave' => 'cualquiera']);

        $this->assertStringContainsString('Cocina no usa el panel', $r->cuerpo);
        $this->assertNull(Sesion::get('usuario_id'));
    }

    public function testUsuarioDesactivadoNoEntra(): void
    {
        DB::consulta("UPDATE usuarios SET activo = 0 WHERE usuario = 'luis'");

        $r = $this->post('/login', ['modo' => 'clave', 'usuario' => 'luis', 'clave' => 'Pahuichi2026']);

        $this->assertStringContainsString('Tu usuario está desactivado', $r->cuerpo);
    }

    public function testSinSesionLasPantallasLlevanAlLogin(): void
    {
        $this->assertRedirige('/login', $this->get('/caja'));
        $this->assertRedirige('/login', $this->get('/'));
    }

    public function testPostSinTokenCsrfSeRechaza(): void
    {
        $r = App::crear()->manejar(new Peticion('POST', '/login', [], ['modo' => 'pin', 'pin' => '4821', '_csrf' => 'falso']));

        $this->assertSame(403, $r->estado);
        $this->assertNull(Sesion::get('usuario_id'));
    }

    public function testInactividadDe15MinutosCierraLaSesion(): void
    {
        Reloj::fijar('2026-10-15 12:00:00');
        $this->entrarComo('admin');
        $this->assertSame(200, $this->get('/reportes')->estado);

        Reloj::fijar('2026-10-15 12:14:00');
        $this->assertSame(200, $this->get('/reportes')->estado);

        Reloj::fijar('2026-10-15 12:29:30');
        $this->assertRedirige('/login?estado=inactividad', $this->get('/reportes'));
        $this->assertNull(Sesion::get('usuario_id'));
        $this->assertCount(1, Bitacora::ultimos('sesion.inactividad'));
    }

    public function testElCajeroNoSeCierraPorInactividad(): void
    {
        Reloj::fijar('2026-10-15 12:00:00');
        $this->entrarComo('mariela', 'pin');

        Reloj::fijar('2026-10-15 15:00:00');
        $this->assertSame(200, $this->get('/caja')->estado);
    }

    public function testSalirCierraLaSesion(): void
    {
        $this->entrarComo('admin');

        $this->assertRedirige('/login?estado=salida', $this->post('/salir'));
        $this->assertRedirige('/login', $this->get('/reportes'));
        $this->assertCount(1, Bitacora::ultimos('sesion.salida'));
    }

    public function testUsuarioDesactivadoPierdeLaSesionAbierta(): void
    {
        $this->entrarComo('luis');
        DB::consulta("UPDATE usuarios SET activo = 0 WHERE usuario = 'luis'");

        $this->assertRedirige('/login', $this->get('/mesero'));
    }

    public function testContrasenaTemporalObligaACambiarla(): void
    {
        DB::consulta("UPDATE usuarios SET debe_cambiar_clave = 1 WHERE usuario = 'luis'");
        $this->post('/login', ['modo' => 'clave', 'usuario' => 'luis', 'clave' => 'Pahuichi2026']);

        $this->assertRedirige('/cambiar-clave', $this->get('/mesero'));

        $r = $this->post('/cambiar-clave', ['nueva' => 'corta', 'repetir' => 'corta']);
        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString('al menos 8 caracteres', $r->cuerpo);

        $this->assertRedirige('/mesero', $this->post('/cambiar-clave', ['nueva' => 'Somo2026x', 'repetir' => 'Somo2026x']));
        $this->assertSame(200, $this->get('/mesero')->estado);
        $this->assertTrue(password_verify('Somo2026x', DB::valor("SELECT password_hash FROM usuarios WHERE usuario = 'luis'")));
    }

    public function testConSesionIniciadaElLoginLlevaAlInicio(): void
    {
        $this->entrarComo('mariela', 'pin');

        $this->assertRedirige('/pos', $this->get('/login'));
    }
}
