<?php

declare(strict_types=1);

namespace Tests\Integracion;

use App\Core\DB;
use App\Core\Reloj;
use App\Core\Respuesta;
use App\Models\Bitacora;
use App\Models\Factura;
use Tests\CasoPrueba;

/**
 * HU-06: autorización con el PIN del administrador (PIN de prueba 1234) para aplicar
 * un descuento manual. El PIN entrega un permiso de un solo uso que vence en 5 minutos;
 * el cobro lo gasta y guarda monto, motivo y quién autorizó.
 */
final class DescuentoManualTest extends CasoPrueba
{
    private int $caja;
    private int $plato;

    protected function setUp(): void
    {
        parent::setUp();
        Reloj::fijar('2026-10-21 12:00:00');
        $this->entrarComo('mariela', 'pin');
        $this->post('/caja/abrir', ['turno_id' => (int) DB::valor("SELECT id FROM turnos WHERE clave = 'mediodia'"), 'fondo' => '300']);
        $this->caja = (int) DB::valor("SELECT id FROM cajas WHERE estado = 'abierta'");
        $this->plato = (int) DB::valor("SELECT id FROM productos WHERE codigo = 'PL-01'"); // Bs 40
    }

    private function autorizar(string $pin, string $monto = '10', string $motivo = 'Demora en cocina'): Respuesta
    {
        return $this->post('/autorizaciones', ['accion' => 'descuento', 'pin' => $pin, 'monto' => $monto, 'motivo' => $motivo]);
    }

    private function token(string $monto = '10'): string
    {
        $r = $this->autorizar('1234', $monto);
        $this->assertSame(200, $r->estado, $r->cuerpo);
        return json_decode($r->cuerpo, true)['token'];
    }

    /** Cobra dos Majaditos (Bs 80) con el descuento indicado. */
    private function cobrar(string $descuento, string $token, ?string $uuid = null): Respuesta
    {
        return $this->post('/pos/cobrar', [
            'uuid' => $uuid ?? '12345678-1234-4123-a123-' . bin2hex(random_bytes(6)),
            'caja_id' => (string) $this->caja, 'tipo' => 'llevar', 'mesa' => '', 'exacto' => '1',
            'total_esperado' => '80.00', 'descuento' => $descuento, 'autorizacion' => $token,
            'lineas' => json_encode([['id' => $this->plato, 'cantidad' => 2, 'nota' => '']]),
        ]);
    }

    /**
     * Escenario: PIN correcto
     *   Dado el cajero pide un descuento de Bs 10 por «Demora en cocina»
     *   Cuando el administrador escribe su PIN
     *   Entonces el descuento queda autorizado a su nombre, por única vez
     */
    public function testElPinDelAdministradorAutoriza(): void
    {
        $r = $this->autorizar('1234');

        $this->assertSame(200, $r->estado, $r->cuerpo);
        $a = json_decode($r->cuerpo, true);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/D', $a['token']);
        $this->assertSame('Ana Lucía Vaca Saucedo', $a['autorizado_por']);
        $this->assertSame('10.00', $a['monto']);
        $this->assertSame('Demora en cocina', $a['motivo']);
    }

    /**
     * Escenario: PIN incorrecto
     *   Dado el cajero pide un descuento
     *   Cuando se escribe un PIN errado, o el PIN de un cajero
     *   Entonces no se autoriza, se avisa cuántos intentos quedan y queda en la bitácora
     */
    public function testUnPinErradoODeCajeroNoAutoriza(): void
    {
        $errado = $this->autorizar('9999');
        $cajero = $this->autorizar('4821'); // PIN de Mariela

        $this->assertSame(422, $errado->estado);
        $this->assertSame('PIN del administrador incorrecto. Quedan 4 intentos.', json_decode($errado->cuerpo, true)['error']);
        $this->assertSame(422, $cajero->estado);
        $this->assertSame('PIN del administrador incorrecto. Quedan 3 intentos.', json_decode($cajero->cuerpo, true)['error']);
        $this->assertArrayNotHasKey('token', json_decode($cajero->cuerpo, true));
        $this->assertCount(2, Bitacora::ultimos('autorizacion.fallo'));
    }

    /**
     * Escenario: Bloqueo tras 5 PIN errados
     *   Dado se escribieron 5 PIN errados seguidos
     *   Cuando se escribe el PIN correcto
     *   Entonces sigue bloqueado 15 minutos; después vuelve a autorizar
     */
    public function testCincoPinErradosBloquean15Minutos(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->autorizar('0000');
        }
        $quinto = $this->autorizar('0000');
        $this->assertSame('Demasiados PIN errados. La autorización se habilita a las 12:15.', json_decode($quinto->cuerpo, true)['error']);
        $this->assertCount(1, Bitacora::ultimos('autorizacion.bloqueo'));

        $this->assertSame(422, $this->autorizar('1234')->estado);

        Reloj::fijar('2026-10-21 12:16:00');
        $this->assertSame(200, $this->autorizar('1234')->estado);
    }

    public function testElMontoYElMotivoSonObligatorios(): void
    {
        $this->assertSame('El descuento debe ser mayor que cero.', json_decode($this->autorizar('1234', '0')->cuerpo, true)['error']);
        $this->assertSame('Escribe el monto del descuento, con hasta dos decimales.', json_decode($this->autorizar('1234', '10,555')->cuerpo, true)['error']);
        $this->assertSame('Escribe el motivo del descuento (de 3 a 120 caracteres).', json_decode($this->autorizar('1234', '10', '  ')->cuerpo, true)['error']);
        $this->assertSame('Esa acción no necesita autorización.', json_decode($this->post('/autorizaciones', ['accion' => 'otra', 'pin' => '1234', 'monto' => '1', 'motivo' => 'abc'])->cuerpo, true)['error']);
    }

    /**
     * Escenario: Descuento aplicado
     *   Dado un pedido de Bs 80 con un descuento autorizado de Bs 10
     *   Cuando cobro
     *   Entonces la venta y la factura muestran subtotal 80, descuento 10 y total 70, y la bitácora dice quién autorizó
     */
    public function testElDescuentoAutorizadoSeCobra(): void
    {
        $r = $this->cobrar('10.00', $this->token());

        $this->assertSame(200, $r->estado, $r->cuerpo);
        $id = (int) json_decode($r->cuerpo, true)['id'];
        $f = Factura::buscar($id);
        $venta = DB::fila('SELECT subtotal, descuento, total, descuento_motivo, descuento_autorizado_por FROM ventas WHERE id = ?', [$f['venta_id']]);
        $admin = (int) DB::valor("SELECT id FROM usuarios WHERE usuario = 'admin'");
        $this->assertSame(['subtotal' => '80.00', 'descuento' => '10.00', 'total' => '70.00', 'descuento_motivo' => 'Demora en cocina', 'descuento_autorizado_por' => $admin], $venta);
        $this->assertSame('70.00', (string) $f['monto_total']);
        $this->assertSame('70.00', (string) DB::valor('SELECT monto FROM pagos WHERE venta_id = ?', [$f['venta_id']]));
        $this->assertSame('SETENTA 00/100 BOLIVIANOS', $f['snapshot']['literal']);

        $html = $this->get('/facturas/' . $id)->cuerpo;
        $this->assertStringContainsString('<div><dt>DESCUENTO Bs</dt><dd>10,00</dd></div>', $html);
        $this->assertStringContainsString('<div><dt>Descuento</dt><dd>10,00</dd></div>', $html);

        $registro = Bitacora::ultimos('venta.descuento_manual');
        $this->assertCount(1, $registro);
        $detalle = json_decode((string) $registro[0]['detalle'], true);
        $this->assertSame(['10.00', 'Demora en cocina', 'Ana Lucía Vaca Saucedo'], [$detalle['monto'], $detalle['motivo'], $detalle['autorizado_por']]);
        $this->assertSame((int) DB::valor("SELECT id FROM usuarios WHERE usuario = 'mariela'"), (int) $registro[0]['usuario_id']);
    }

    /**
     * Escenario: El permiso es de un solo uso
     *   Dado ya cobré con un descuento autorizado
     *   Cuando intento usar el mismo permiso en otro pedido
     *   Entonces se rechaza y hay que pedir el PIN otra vez
     */
    public function testElPermisoNoSeReutiliza(): void
    {
        $token = $this->token();
        $uuid = '12345678-1234-4123-a123-' . bin2hex(random_bytes(6));
        $primero = $this->cobrar('10.00', $token, $uuid);
        $this->assertSame(200, $primero->estado);

        // Reintentar el mismo pedido devuelve la misma factura sin gastar otro permiso
        $reintento = $this->cobrar('10.00', $token, $uuid);
        $this->assertSame(200, $reintento->estado, $reintento->cuerpo);
        $this->assertSame(json_decode($primero->cuerpo, true)['id'], json_decode($reintento->cuerpo, true)['id']);

        $otro = $this->cobrar('10.00', $token);
        $this->assertSame(422, $otro->estado);
        $this->assertSame('invalido', json_decode($otro->cuerpo, true)['descuento']);
        $this->assertSame(1, (int) DB::valor('SELECT COUNT(*) FROM ventas'));
    }

    public function testElPermisoVenceALosCincoMinutos(): void
    {
        $token = $this->token();
        Reloj::fijar('2026-10-21 12:05:01');

        $r = $this->cobrar('10.00', $token);

        $this->assertSame(422, $r->estado);
        $this->assertSame('La autorización del descuento venció o ya se usó. Pide el PIN del administrador otra vez.', json_decode($r->cuerpo, true)['error']);
    }

    /**
     * Escenario: Descuento mayor que el subtotal
     *   Dado un pedido de Bs 80
     *   Cuando el descuento autorizado es de Bs 80 o más
     *   Entonces el cobro se rechaza sin guardar la venta ni gastar número de factura
     */
    public function testElDescuentoDebeSerMenorQueElSubtotal(): void
    {
        $r = $this->cobrar('80.00', $this->token('80'));

        $this->assertSame(422, $r->estado);
        $this->assertSame('El descuento debe ser menor que el subtotal del pedido.', json_decode($r->cuerpo, true)['error']);
        $this->assertSame(0, (int) DB::valor('SELECT COUNT(*) FROM facturas'));
    }

    public function testSinPermisoOConOtroMontoNoHayDescuento(): void
    {
        $sinPermiso = $this->cobrar('10.00', '');
        $otroMonto = $this->cobrar('15.00', $this->token('10'));

        $this->assertSame(422, $sinPermiso->estado);
        $this->assertSame(422, $otroMonto->estado);
        $this->assertSame('El descuento no coincide con el autorizado. Pide el PIN del administrador otra vez.', json_decode($otroMonto->cuerpo, true)['error']);
        $this->assertSame(0, (int) DB::valor('SELECT COUNT(*) FROM ventas'));
    }
}
