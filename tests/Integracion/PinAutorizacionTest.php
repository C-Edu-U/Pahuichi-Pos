<?php

declare(strict_types=1);

namespace Tests\Integracion;

use App\Core\DB;
use App\Core\Reloj;
use App\Core\Respuesta;
use App\Models\Bitacora;
use Tests\CasoPrueba;

/**
 * HU-06: el PIN del administrador también autoriza la anulación (la anulación en sí es
 * HU-44: por ahora solo se valida el PIN), y cada administrador elige su propio PIN.
 */
final class PinAutorizacionTest extends CasoPrueba
{
    protected function setUp(): void
    {
        parent::setUp();
        Reloj::fijar('2026-10-22 12:00:00');
    }

    /** Cobra un Majadito como Mariela y devuelve el id de la factura. */
    private function venta(): int
    {
        $this->entrarComo('mariela', 'pin');
        $this->post('/caja/abrir', ['turno_id' => (int) DB::valor("SELECT id FROM turnos WHERE clave = 'mediodia'"), 'fondo' => '200']);
        $r = $this->post('/pos/cobrar', [
            'uuid' => '12345678-1234-4123-a123-' . bin2hex(random_bytes(6)),
            'caja_id' => (string) DB::valor("SELECT id FROM cajas WHERE estado = 'abierta'"), 'tipo' => 'llevar', 'mesa' => '', 'exacto' => '1',
            'total_esperado' => '40.00', 'lineas' => json_encode([['id' => (int) DB::valor("SELECT id FROM productos WHERE codigo = 'PL-01'"), 'cantidad' => 1, 'nota' => '']]),
        ]);
        return (int) json_decode($r->cuerpo, true)['id'];
    }

    private function anular(string $pin): Respuesta
    {
        return $this->post('/autorizaciones', ['accion' => 'anulacion', 'pin' => $pin]);
    }

    /**
     * Escenario: Anular pide el PIN del administrador
     *   Dado estoy en los tickets de una venta
     *   Cuando pulso «Anular venta» y el administrador escribe su PIN
     *   Entonces el PIN se valida y se avisa que la anulación llega con HU-44, sin cambiar la venta
     */
    public function testAnularPideElPinYAvisaQueLlegaConHu44(): void
    {
        $id = $this->venta();

        $html = $this->get('/facturas/' . $id)->cuerpo;
        $this->assertStringContainsString('data-abrir-modal="modal-anular"', $html);
        $this->assertStringContainsString('<dialog class="modal" id="modal-anular"', $html);
        $this->assertStringContainsString('<script src="/assets/js/anular.js"></script>', $html);

        $r = $this->anular('1234');

        $this->assertSame(200, $r->estado, $r->cuerpo);
        $this->assertSame('PIN correcto. La anulación de ventas llega con HU-44: esta venta no se modificó.', json_decode($r->cuerpo, true)['aviso']);
        $this->assertSame('cobrada', DB::valor('SELECT v.estado FROM ventas v JOIN facturas f ON f.venta_id = v.id WHERE f.id = ?', [$id]));
        $this->assertSame('emitida', DB::valor('SELECT estado FROM facturas WHERE id = ?', [$id]));
        $this->assertCount(0, Bitacora::ultimos('venta.anulacion'));
    }

    public function testUnPinErradoODeCajeroNoAutorizaLaAnulacion(): void
    {
        $this->venta();

        $errado = $this->anular('9999');
        $cajero = $this->anular('4821');

        $this->assertSame('PIN del administrador incorrecto. Quedan 4 intentos.', json_decode($errado->cuerpo, true)['error']);
        $this->assertSame('PIN del administrador incorrecto. Quedan 3 intentos.', json_decode($cajero->cuerpo, true)['error']);
        $detalle = json_decode((string) Bitacora::ultimos('autorizacion.fallo')[0]['detalle'], true);
        $this->assertSame('anulacion', $detalle['accion']);
    }

    private function miPin(string $pin, string $confirmacion = '', string $clave = 'Pahuichi2026'): Respuesta
    {
        return $this->post('/usuarios/mi-pin', ['clave' => $clave, 'pin' => $pin, 'confirmacion' => $confirmacion ?: $pin]);
    }

    /**
     * Escenario: El administrador elige su PIN de autorización
     *   Dado soy administrador
     *   Cuando confirmo mi contraseña y escribo un PIN nuevo dos veces
     *   Entonces el PIN nuevo autoriza en caja y el anterior ya no
     */
    public function testElAdministradorEligeSuPin(): void
    {
        $this->entrarComo('admin');
        $this->assertStringContainsString('id="form-mi-pin"', $this->get('/usuarios')->cuerpo);

        $this->assertRedirige('/usuarios', $this->miPin('5826'));
        $this->assertCount(1, Bitacora::ultimos('usuario.cambio_pin'));

        $this->venta();
        $this->assertSame(200, $this->anular('5826')->estado);
        $this->assertSame(422, $this->anular('1234')->estado);
    }

    public static function invalidos(): array
    {
        return [
            'contraseña errada' => ['5826', '5826', 'otra', 'mp-clave', 'Tu contraseña actual no coincide.'],
            'corto' => ['582', '582', 'Pahuichi2026', 'mp-pin', 'El PIN tiene de 4 a 6 dígitos.'],
            'letras' => ['58a6', '58a6', 'Pahuichi2026', 'mp-pin', 'El PIN tiene de 4 a 6 dígitos.'],
            'seguidos' => ['3456', '3456', 'Pahuichi2026', 'mp-pin', 'Elige un PIN menos obvio: sin dígitos repetidos ni seguidos (1111, 1234).'],
            'repetidos' => ['7777', '7777', 'Pahuichi2026', 'mp-pin', 'Elige un PIN menos obvio: sin dígitos repetidos ni seguidos (1111, 1234).'],
            'el de un cajero' => ['4821', '4821', 'Pahuichi2026', 'mp-pin', 'Ese PIN ya lo usa otra persona. Elige otro.'],
            'no coinciden' => ['5826', '5827', 'Pahuichi2026', 'mp-confirmacion', 'Los dos PIN no coinciden.'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidos')]
    public function testValidaElPinNuevo(string $pin, string $confirmacion, string $clave, string $campo, string $mensaje): void
    {
        $this->entrarComo('admin');
        $antes = DB::valor("SELECT pin_hash FROM usuarios WHERE usuario = 'admin'");

        $r = $this->miPin($pin, $confirmacion, $clave);

        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString(e($mensaje), $r->cuerpo);
        $this->assertStringContainsString('id="' . $campo . '"', $r->cuerpo);
        $this->assertStringContainsString('id="form-mi-pin" method="post" action="/usuarios/mi-pin" novalidate aria-labelledby', $r->cuerpo);   // queda abierto
        $this->assertSame($antes, DB::valor("SELECT pin_hash FROM usuarios WHERE usuario = 'admin'"));
    }

    public function testElCajeroNoGestionaPines(): void
    {
        $this->entrarComo('mariela', 'pin');

        $this->assertSame(403, $this->miPin('5826')->estado);
    }
}
