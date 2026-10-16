<?php

declare(strict_types=1);

namespace Tests\Integracion;

use App\Core\DB;
use App\Core\Sesion;
use App\Models\Bitacora;
use Tests\CasoPrueba;

/** HU-03: gestión de usuarios. */
final class UsuariosTest extends CasoPrueba
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

    /** Toma las credenciales temporales que deja la acción para la página siguiente. */
    private function credenciales(): array
    {
        $c = Sesion::get('_flash')['credenciales'] ?? null;
        $this->assertIsArray($c, 'La acción debía mostrar credenciales temporales');
        return $c['claves'];
    }

    public function testListaLosUsuarios(): void
    {
        $r = $this->get('/usuarios');

        $this->assertSame(200, $r->estado);
        foreach (['Ana Lucía Vaca Saucedo', 'Mariela Suárez Añez', 'Teresa Paz Justiniano'] as $nombre) {
            $this->assertStringContainsString(e($nombre), $r->cuerpo);
        }
        $this->assertStringNotContainsString('password_hash', $r->cuerpo);
        $this->assertStringNotContainsString('$2y$', $r->cuerpo);
    }

    public function testAltaDeCajeroConContrasenaYPinTemporales(): void
    {
        $r = $this->post('/usuarios', ['nombre' => 'Rosa Melgar', 'usuario' => 'rosa', 'rol' => 'cajero']);

        $this->assertRedirige('/usuarios', $r);
        $claves = $this->credenciales();
        $this->assertMatchesRegularExpression('/^[a-z]+-\d{4}$/', $claves['Contraseña']);
        $this->assertMatchesRegularExpression('/^\d{4}$/', $claves['PIN']);

        // La credencial se muestra una vez y funciona
        $pagina = $this->get('/usuarios')->cuerpo;
        $this->assertStringContainsString($claves['PIN'], $pagina);
        $this->assertStringNotContainsString($claves['PIN'], $this->get('/usuarios')->cuerpo);

        $u = DB::fila("SELECT * FROM usuarios WHERE usuario = 'rosa'");
        $this->assertSame('cajero', $u['rol']);
        $this->assertSame(1, (int) $u['debe_cambiar_clave']);
        $this->assertTrue(password_verify($claves['PIN'], $u['pin_hash']));
        $this->assertCount(1, Bitacora::ultimos('usuario.alta'));

        $this->post('/salir');
        $this->assertRedirige('/pos', $this->post('/login', ['modo' => 'pin', 'pin' => $claves['PIN']]));
    }

    public function testElPinGeneradoNoRepiteElDeOtroUsuario(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/usuarios', ['nombre' => "Cajero {$i}", 'usuario' => "caja{$i}", 'rol' => 'cajero']);
            $pin = $this->credenciales()['PIN'];
            $this->assertNotContains($pin, ['4821', '7350', '1234']);
            $iguales = 0;
            foreach (DB::filas('SELECT pin_hash FROM usuarios WHERE pin_hash IS NOT NULL') as $f) {
                $iguales += password_verify($pin, $f['pin_hash']) ? 1 : 0;
            }
            $this->assertSame(1, $iguales);
        }
    }

    public function testAltaDeCocinaSinCredenciales(): void
    {
        $this->post('/usuarios', ['nombre' => 'Juana Rojas', 'usuario' => 'juana', 'rol' => 'cocina']);

        $this->assertNull(Sesion::get('_flash')['credenciales'] ?? null);
        $this->assertNull(DB::valor("SELECT password_hash FROM usuarios WHERE usuario = 'juana'"));
    }

    public function testAltaValidaLosCampos(): void
    {
        $r = $this->post('/usuarios', ['nombre' => '', 'usuario' => 'Mariela', 'rol' => 'cajero']);

        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString('Escribe el nombre completo.', $r->cuerpo);
        $this->assertStringContainsString('Ese usuario ya existe.', $r->cuerpo);

        $r = $this->post('/usuarios', ['nombre' => 'X', 'usuario' => 'con espacio', 'rol' => 'jefe']);
        $this->assertStringContainsString('Usa de 3 a 20 minúsculas o números', $r->cuerpo);
        $this->assertStringContainsString('Elige un rol de la lista.', $r->cuerpo);
        $this->assertSame(5, (int) DB::valor('SELECT COUNT(*) FROM usuarios'));
    }

    public function testCambioDeRolAMeseroACajeroGeneraPin(): void
    {
        $this->post('/usuarios/' . $this->id('luis') . '/rol', ['rol' => 'cajero']);

        $this->assertSame('cajero', DB::valor("SELECT rol FROM usuarios WHERE usuario = 'luis'"));
        $this->assertArrayHasKey('PIN', $this->credenciales());
        $this->assertCount(1, Bitacora::ultimos('usuario.rol'));
    }

    public function testNoPuedeCambiarseSuPropioRolNiDesactivarse(): void
    {
        $this->post('/usuarios/' . $this->id('admin') . '/rol', ['rol' => 'cajero']);
        $this->post('/usuarios/' . $this->id('admin') . '/estado', ['activo' => '0']);

        $u = DB::fila("SELECT rol, activo FROM usuarios WHERE usuario = 'admin'");
        $this->assertSame('administrador', $u['rol']);
        $this->assertSame(1, (int) $u['activo']);
    }

    public function testDesactivarYActivar(): void
    {
        $id = $this->id('jorge');

        $this->post("/usuarios/{$id}/estado", ['activo' => '0']);
        $this->assertSame(0, (int) DB::valor('SELECT activo FROM usuarios WHERE id = ?', [$id]));
        $this->assertStringContainsString('ya no puede ingresar', Sesion::get('_flash')['aviso']);

        $this->post("/usuarios/{$id}/estado", ['activo' => '1']);
        $this->assertSame(1, (int) DB::valor('SELECT activo FROM usuarios WHERE id = ?', [$id]));
        $this->assertCount(1, Bitacora::ultimos('usuario.desactivar'));
        $this->assertCount(1, Bitacora::ultimos('usuario.activar'));
    }

    public function testDesbloquear(): void
    {
        $id = $this->id('jorge');
        DB::consulta("UPDATE usuarios SET bloqueado_hasta = '2099-01-01 00:00:00', intentos_fallidos = 3 WHERE id = ?", [$id]);
        $this->assertStringContainsString('Bloqueado hasta', $this->get('/usuarios')->cuerpo);

        $this->post("/usuarios/{$id}/desbloquear");

        $this->assertNull(DB::valor('SELECT bloqueado_hasta FROM usuarios WHERE id = ?', [$id]));
    }

    public function testResetearContrasenaObligaACambiarla(): void
    {
        $this->post('/usuarios/' . $this->id('luis') . '/clave');
        $clave = $this->credenciales()['Contraseña'];

        $this->post('/salir');
        $this->assertRedirige('/cambiar-clave', $this->post('/login', ['modo' => 'clave', 'usuario' => 'luis', 'clave' => $clave]));
        $this->assertCount(1, Bitacora::ultimos('usuario.reseteo_clave'));
    }

    public function testResetearPin(): void
    {
        $this->post('/usuarios/' . $this->id('mariela') . '/pin');
        $pin = $this->credenciales()['PIN'];

        $this->post('/salir');
        $this->assertSame(422, $this->post('/login', ['modo' => 'pin', 'pin' => '4821'])->estado, 'El PIN anterior ya no sirve');
        $this->assertRedirige('/pos', $this->post('/login', ['modo' => 'pin', 'pin' => $pin]));
    }

    public function testElMeseroNoTienePin(): void
    {
        $this->post('/usuarios/' . $this->id('luis') . '/pin');

        $this->assertStringContainsString('Solo el cajero y el administrador usan PIN', Sesion::get('_flash')['aviso']);
    }

    public function testCajeroNoPuedeGestionarUsuarios(): void
    {
        $this->entrarComo('mariela', 'pin');

        $this->assertSame(403, $this->post('/usuarios', ['nombre' => 'Intruso', 'usuario' => 'intruso', 'rol' => 'administrador'])->estado);
        $this->assertSame(403, $this->post('/usuarios/' . $this->id('admin') . '/clave')->estado);
        $this->assertNull(DB::valor("SELECT id FROM usuarios WHERE usuario = 'intruso'"));
    }
}
