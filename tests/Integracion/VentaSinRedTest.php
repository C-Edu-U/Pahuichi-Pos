<?php

declare(strict_types=1);

namespace Tests\Integracion;

use App\Core\DB;
use App\Core\Reloj;
use App\Core\Respuesta;
use App\Models\Bitacora;
use App\Services\VentaService;
use DateTimeImmutable;
use Tests\CasoPrueba;

/**
 * HU-36: ventas hechas sin red. El POS las guarda con su uuid y su hora y las envía al
 * volver la red; el servidor registra cada una una sola vez y evalúa sus promociones a
 * la hora en que se vendió, si esa hora es creíble.
 */
final class VentaSinRedTest extends CasoPrueba
{
    private int $caja;
    private int $majadito;   // Bs 40

    protected function setUp(): void
    {
        parent::setUp();
        Reloj::fijar('2026-10-22 12:00:00');
        $this->majadito = (int) DB::valor("SELECT id FROM productos WHERE codigo = 'PL-01'");
        $this->entrarComo('mariela', 'pin');
        $this->post('/caja/abrir', ['turno_id' => (int) DB::valor("SELECT id FROM turnos WHERE clave = 'mediodia'"), 'fondo' => '200']);
        $this->caja = (int) DB::valor("SELECT id FROM cajas WHERE estado = 'abierta'");
        // «Almuerzo»: 10 % al Majadito hasta las 13:00
        $id = DB::insertar("INSERT INTO promociones (nombre, valor_tipo, valor, producto_id, activo) VALUES ('Almuerzo', 'porcentaje', '10.00', ?, 1)", [$this->majadito]);
        DB::consulta("UPDATE promociones SET hora_desde = '11:00:00', hora_hasta = '13:00:00' WHERE id = ?", [$id]);
    }

    /** La venta tal como la guarda la cola del POS: 2 Majaditos, con su uuid y su hora. */
    private function venta(string $uuid, string $momento, string $promociones): array
    {
        return [
            'uuid' => $uuid, 'caja_id' => (string) $this->caja, 'tipo' => 'mesa', 'mesa' => '4', 'exacto' => '1',
            'total_esperado' => '80.00', 'promociones' => $promociones, 'regalos' => '0.00', 'momento' => $momento,
            'lineas' => json_encode([['id' => $this->majadito, 'cantidad' => 2, 'nota' => 'sin cebolla']]),
        ];
    }

    private function enviar(array $venta): Respuesta
    {
        return $this->post('/pos/cobrar', $venta);
    }

    /**
     * Escenario 3: Sin duplicados
     *   Dado una venta con su identificador único enviada dos veces por un reintento
     *   Cuando llega al servidor
     *   Entonces solo se registra una venta
     */
    public function testLaMismaVentaEnviadaDosVecesSeRegistraUnaVez(): void
    {
        $venta = $this->venta('6f1c2a3b-4d5e-4f60-8a7b-9c0d1e2f3a4b', '2026-10-22T11:58:00', '8.00');

        Reloj::fijar('2026-10-22 12:20:00');
        $primera = $this->enviar($venta);
        // La respuesta se perdió: el POS reintenta con el mismo uuid
        $segunda = $this->enviar($venta);

        $this->assertSame(200, $primera->estado, $primera->cuerpo);
        $this->assertSame(200, $segunda->estado, $segunda->cuerpo);
        $this->assertSame(json_decode($primera->cuerpo, true), json_decode($segunda->cuerpo, true));
        $this->assertSame(1, (int) DB::valor('SELECT COUNT(*) FROM ventas'));
        $this->assertSame(1, (int) DB::valor('SELECT COUNT(*) FROM facturas'));
        $this->assertSame(1, (int) DB::valor('SELECT COUNT(*) FROM pagos'));
        $this->assertSame(1, (int) DB::valor('SELECT COUNT(*) FROM detalle_venta'));
        $this->assertCount(1, Bitacora::ultimos('venta.cobro'));
    }

    public function testElMismoUuidConOtraHoraEsOtroPedidoYSeRechaza(): void
    {
        $this->assertSame(200, $this->enviar($this->venta('6f1c2a3b-4d5e-4f60-8a7b-9c0d1e2f3a4b', '2026-10-22T11:58:00', '8.00'))->estado);

        $r = $this->enviar($this->venta('6f1c2a3b-4d5e-4f60-8a7b-9c0d1e2f3a4b', '2026-10-22T11:59:00', '8.00'));

        $this->assertSame(422, $r->estado);
        $this->assertSame(1, (int) DB::valor('SELECT COUNT(*) FROM ventas'));
    }

    /**
     * Escenario: La promoción de la hora en que se vendió
     *   Dado «Almuerzo» vale hasta las 13:00
     *   Y vendí 2 Majaditos sin red a las 12:55 con su descuento
     *   Cuando la venta llega a las 13:10, al volver la red
     *   Entonces se registra con el descuento con que se cobró
     *   Y la venta y la factura llevan la hora de llegada
     */
    public function testLaPromocionSeEvaluaALaHoraDeLaVenta(): void
    {
        Reloj::fijar('2026-10-22 13:10:00');

        $r = $this->enviar($this->venta('7a1c2a3b-4d5e-4f60-8a7b-9c0d1e2f3a4b', '2026-10-22T12:55:00', '8.00'));

        $this->assertSame(200, $r->estado, $r->cuerpo);
        $this->assertSame('72.00', DB::valor('SELECT total FROM ventas'));
        $this->assertSame('2026-10-22 13:10:00', DB::valor('SELECT creado_en FROM ventas'));
        $this->assertSame('2026-10-22 13:10:00', DB::valor('SELECT fecha_emision FROM facturas'));
        $this->assertStringContainsString('"vendida_sin_red":"22\/10\/2026 12:55:00"', (string) Bitacora::ultimos('venta.cobro')[0]['detalle']);
    }

    public function testConRedLaVentaNoSeMarcaComoSinRed(): void
    {
        $r = $this->enviar($this->venta('8a1c2a3b-4d5e-4f60-8a7b-9c0d1e2f3a4b', '2026-10-22T12:00:00', '8.00'));

        $this->assertSame(200, $r->estado, $r->cuerpo);
        $this->assertStringNotContainsString('vendida_sin_red', (string) Bitacora::ultimos('venta.cobro')[0]['detalle']);
    }

    /** Una hora que no es creíble no se usa: vale la de llegada (y ahí la promoción ya terminó). */
    public function testUnaHoraNoCreibleUsaLaDeLlegada(): void
    {
        Reloj::fijar('2026-10-22 13:10:00');

        // Antes de abrir la caja (11:59 vs 12:00), del futuro, y mal escrita
        foreach (['2026-10-22T11:50:00', '2026-10-22T13:30:00', '2026-10-22 12:55'] as $i => $momento) {
            $r = $this->enviar($this->venta("9a1c2a3b-4d5e-4f60-8a7b-9c0d1e2f3a4{$i}", $momento, '8.00'));
            $this->assertSame(422, $r->estado, $momento);
            $this->assertStringContainsString('Cambió una promoción', $r->cuerpo);
        }
        $this->assertSame(0, (int) DB::valor('SELECT COUNT(*) FROM ventas'));
    }

    /**
     * Escenario 2 (lo que se puede comprobar sin navegador): todo lo que la pantalla del POS
     * carga está en la lista del service worker, y la página guardada sabe de quién es.
     */
    public function testElPosSePuedeAbrirDesdeLaCache(): void
    {
        $html = $this->get('/pos')->cuerpo;
        $sw = (string) file_get_contents(dirname(__DIR__, 2) . '/public/sw.js');

        preg_match_all('#(?:href|src)="(/assets/(?:css|js)/[^"?]+)"#', $html, $m);
        $recursos = array_unique(array_filter($m[1], static fn (string $r): bool => !str_ends_with($r, 'print.css')));
        $this->assertNotEmpty($recursos);
        foreach ($recursos as $recurso) {
            $this->assertStringContainsString("'{$recurso}'", $sw, "{$recurso} no está en la caché del service worker");
        }
        $this->assertStringContainsString("'/sin-conexion.html'", $sw);
        $this->assertFileExists(dirname(__DIR__, 2) . '/public/sin-conexion.html');

        $mariela = (int) DB::valor("SELECT id FROM usuarios WHERE usuario = 'mariela'");
        $this->assertStringContainsString('data-usuario="' . $mariela . '"', $html);
        $this->assertStringContainsString('id="cola-ventas"', $html);
    }

    public function testElMomentoDeVenta(): void
    {
        $ahora = new DateTimeImmutable('2026-10-22 20:00:00');
        $caja = ['abierta_en' => '2026-10-22 07:00:00'];
        $momento = static fn (mixed $t): string => VentaService::momentoDeVenta($t, $ahora, $caja)->format('Y-m-d H:i:s');

        $this->assertSame('2026-10-22 08:30:00', $momento('2026-10-22T08:30:00'));   // dentro de la caja y de las 12 h
        $this->assertSame('2026-10-22 20:00:00', $momento('2026-10-22T07:59:00'));   // más de 12 h atrás
        $this->assertSame('2026-10-22 20:00:00', $momento('2026-10-22T06:30:00'));   // antes de abrir la caja
        $this->assertSame('2026-10-22 20:00:00', $momento('2026-10-22T20:01:00'));   // del futuro (reloj adelantado)
        $this->assertSame('2026-10-22 20:00:00', $momento('2026-02-30T12:00:00'));   // fecha que no existe
        $this->assertSame('2026-10-22 20:00:00', $momento(null));                    // POS anterior, sin hora
    }
}
