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
 * HU-46: la promoción se aplica sola en el punto de venta y el servidor la recalcula al
 * cobrar. La venta guarda el descuento en la línea; la copia de caja y la factura lo muestran.
 */
final class CobroConPromocionTest extends CasoPrueba
{
    private int $caja;
    private int $majadito;   // Bs 40
    private int $locro;      // Bs 35
    private int $moco;       // Bs 10
    private int $platos;

    protected function setUp(): void
    {
        parent::setUp();
        Reloj::fijar('2026-10-22 12:00:00');
        $this->majadito = (int) DB::valor("SELECT id FROM productos WHERE codigo = 'PL-01'");
        $this->locro = (int) DB::valor("SELECT id FROM productos WHERE codigo = 'PL-02'");
        $this->moco = (int) DB::valor("SELECT id FROM productos WHERE codigo = 'RE-01'");
        $this->platos = (int) DB::valor("SELECT id FROM categorias WHERE nombre = 'Platos'");
        $this->entrarComo('mariela', 'pin');
        $this->post('/caja/abrir', ['turno_id' => (int) DB::valor("SELECT id FROM turnos WHERE clave = 'mediodia'"), 'fondo' => '200']);
        $this->caja = (int) DB::valor("SELECT id FROM cajas WHERE estado = 'abierta'");
    }

    private function promo(string $nombre, string $tipo, string $valor, ?int $producto, ?int $categoria = null, int $activo = 1): int
    {
        return DB::insertar('INSERT INTO promociones (nombre, valor_tipo, valor, producto_id, categoria_id, activo) VALUES (?, ?, ?, ?, ?, ?)',
            [$nombre, $tipo, $valor, $producto, $categoria, $activo]);
    }

    /** @param array<int, int> $cantidades producto => cantidad */
    private function cobrar(array $cantidades, string $bruto, string $promociones, array $extra = []): Respuesta
    {
        $lineas = [];
        foreach ($cantidades as $id => $n) {
            $lineas[] = ['id' => $id, 'cantidad' => $n, 'nota' => ''];
        }
        return $this->post('/pos/cobrar', $extra + [
            'uuid' => '12345678-1234-4123-a123-' . bin2hex(random_bytes(6)),
            'caja_id' => (string) $this->caja, 'tipo' => 'llevar', 'mesa' => '', 'exacto' => '1',
            'total_esperado' => $bruto, 'promociones' => $promociones, 'lineas' => json_encode($lineas),
        ]);
    }

    /**
     * Escenario: La promoción se aplica sola y se ve como línea de descuento
     *   Dado «Martes de majadito», 10 % al Majadito
     *   Cuando cobro 2 Majaditos y un Mocochinchi (Bs 90)
     *   Entonces la línea del Majadito descuenta Bs 8 y el total es Bs 82
     *   Y la copia de caja muestra la promoción bajo el plato y la factura el descuento de la línea
     */
    public function testLaPromocionSeCobraYSeVeEnLosTickets(): void
    {
        $promo = $this->promo('Martes de majadito', 'porcentaje', '10.00', $this->majadito);

        $r = $this->cobrar([$this->majadito => 2, $this->moco => 1], '90.00', '8.00');

        $this->assertSame(200, $r->estado, $r->cuerpo);
        $f = Factura::buscar((int) json_decode($r->cuerpo, true)['id']);
        $venta = DB::fila('SELECT subtotal, descuento, total FROM ventas WHERE id = ?', [$f['venta_id']]);
        $this->assertSame(['subtotal' => '82.00', 'descuento' => '0.00', 'total' => '82.00'], $venta);
        $lineas = DB::filas('SELECT producto_id, precio_unitario, descuento, subtotal FROM detalle_venta WHERE venta_id = ? ORDER BY producto_id', [$f['venta_id']]);
        $this->assertSame(['8.00', '72.00'], [$lineas[0]['descuento'], $lineas[0]['subtotal']]);
        $this->assertSame(['0.00', '10.00'], [$lineas[1]['descuento'], $lineas[1]['subtotal']]);
        $uso = DB::fila('SELECT promocion_id, etiqueta, monto FROM venta_promociones WHERE venta_id = ?', [$f['venta_id']]);
        $this->assertSame([$promo, 'Martes de majadito', '8.00'], [(int) $uso['promocion_id'], $uso['etiqueta'], $uso['monto']]);
        $this->assertSame('82.00', (string) $f['monto_total']);
        $this->assertSame('82.00', (string) DB::valor('SELECT monto FROM pagos WHERE venta_id = ?', [$f['venta_id']]));
        $this->assertSame('OCHENTA Y DOS 00/100 BOLIVIANOS', $f['snapshot']['literal']);

        $html = $this->get('/facturas/' . $f['id'])->cuerpo;
        $this->assertStringContainsString('<li><span>2 × Majadito de charque</span><span class="num">80,00</span></li><li class="ticket__promo"><span>Martes de majadito</span><span class="num">−8,00</span></li>', $html);
        $this->assertStringContainsString('2,00 × 40,00 − 8,00</span><span class="ticket__sub num">72,00</span>', $html);
        $this->assertStringContainsString('<div><dt>SUBTOTAL Bs</dt><dd>82,00</dd></div>', $html);
        $this->assertStringContainsString('"promociones":"8.00"', (string) Bitacora::ultimos('venta.cobro')[0]['detalle']);
    }

    /**
     * Escenario: Si la promoción cambia mientras se arma el pedido
     *   Dado la pantalla mostraba Bs 8 de promoción
     *   Cuando el administrador pausa la promoción antes del cobro
     *   Entonces el cobro se rechaza sin guardar venta y pide recargar
     */
    public function testUnaPromocionQueCambioRechazaElCobro(): void
    {
        $this->promo('Martes de majadito', 'porcentaje', '10.00', $this->majadito, null, 0);

        $r = $this->cobrar([$this->majadito => 2], '80.00', '8.00');

        $this->assertSame(422, $r->estado);
        $this->assertSame('Cambió una promoción. Recarga el punto de venta y revisa el total antes de cobrar.', json_decode($r->cuerpo, true)['error']);
        $this->assertSame(0, (int) DB::valor('SELECT COUNT(*) FROM ventas'));
    }

    public function testPorCategoriaGanaLaDeMayorBeneficio(): void
    {
        $this->promo('Platos 10 %', 'porcentaje', '10.00', null, $this->platos);
        $mejor = $this->promo('Majadito a 34', 'monto', '6.00', $this->majadito);

        // Majadito: 6 (gana la de Bs 6 sobre el 10 % = 4); Locro: 3,50; total de promoción 9,50
        $r = $this->cobrar([$this->majadito => 1, $this->locro => 1], '75.00', '9.50');

        $this->assertSame(200, $r->estado, $r->cuerpo);
        $usos = DB::filas('SELECT promocion_id, monto FROM venta_promociones ORDER BY monto DESC');
        $this->assertSame([$mejor, '6.00'], [(int) $usos[0]['promocion_id'], $usos[0]['monto']]);
        $this->assertSame('3.50', $usos[1]['monto']);
        $this->assertSame('65.50', (string) DB::valor('SELECT total FROM ventas'));
    }

    /**
     * Escenario: Promoción y descuento manual juntos
     *   Dado 2 Majaditos con 10 % (subtotal Bs 72 tras la promoción)
     *   Cuando el administrador autoriza además Bs 2 de descuento manual
     *   Entonces el total es Bs 70; un descuento manual de Bs 72 o más se rechaza
     */
    public function testConviveConElDescuentoManual(): void
    {
        $this->promo('Martes de majadito', 'porcentaje', '10.00', $this->majadito);
        $token = fn (string $monto): string => json_decode($this->post('/autorizaciones', ['accion' => 'descuento', 'pin' => '1234', 'monto' => $monto, 'motivo' => 'Cortesía'])->cuerpo, true)['token'];

        $mucho = $this->cobrar([$this->majadito => 2], '80.00', '8.00', ['descuento' => '72.00', 'autorizacion' => $token('72')]);
        $this->assertSame('El descuento debe ser menor que el subtotal del pedido.', json_decode($mucho->cuerpo, true)['error']);

        $r = $this->cobrar([$this->majadito => 2], '80.00', '8.00', ['descuento' => '2.00', 'autorizacion' => $token('2')]);
        $this->assertSame(200, $r->estado, $r->cuerpo);
        $this->assertSame(['subtotal' => '72.00', 'descuento' => '2.00', 'total' => '70.00'], DB::fila('SELECT subtotal, descuento, total FROM ventas'));
    }

    public function testElResumenDeCierreSumaLosDescuentosDePromocion(): void
    {
        $this->promo('Martes de majadito', 'porcentaje', '10.00', $this->majadito);
        $this->cobrar([$this->majadito => 2], '80.00', '8.00');

        $resumen = \App\Models\Venta::resumen($this->caja);

        $this->assertSame('8.00', $resumen['descuentos']);
        $this->assertSame('72.00', $resumen['efectivo']);
    }

    /**
     * Escenario: El punto de venta conoce las promociones activas
     *   Dado una promoción activa al Majadito y otra pausada al Locro
     *   Cuando abro el punto de venta
     *   Entonces el Majadito lleva la marca «Promo» y solo la activa viaja al motor del navegador
     */
    public function testElPuntoDeVentaMarcaLosProductosConPromocion(): void
    {
        $activa = $this->promo('Martes de majadito', 'porcentaje', '12.50', $this->majadito);
        $this->promo('Locro pausado', 'monto', '5.00', $this->locro, null, 0);

        $html = $this->get('/pos')->cuerpo;

        preg_match('/data-promociones="([^"]+)"/', $html, $m);
        $promos = json_decode(html_entity_decode($m[1]), true);
        $this->assertSame([['id' => $activa, 'nombre' => 'Martes de majadito', 'valor_tipo' => 'porcentaje', 'valor' => 1250, 'producto_id' => $this->majadito, 'categoria_id' => null]], $promos);
        $this->assertMatchesRegularExpression('/aria-label="Majadito de charque, 40,00 bolivianos, con promoción">.*?<span class="insignia insignia--promo">Promo<\/span>/s', $html);
        $this->assertStringNotContainsString('Locro carretero, 35,00 bolivianos, con promoción', $html);
        $this->assertStringContainsString('<script src="/assets/js/motor-promociones.js"></script>', $html);
    }
}
