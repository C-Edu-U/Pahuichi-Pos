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
 * HU-49: al cobrar valen la fecha, el día y la hora del servidor, el tope de hoy y las condiciones del pedido.
 * El 22/10/2026 es jueves.
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

    private function promo(string $nombre, string $tipo, string $valor, ?int $producto, ?int $categoria = null, int $activo = 1, array $restricciones = []): int
    {
        $id = DB::insertar('INSERT INTO promociones (nombre, valor_tipo, valor, producto_id, categoria_id, activo) VALUES (?, ?, ?, ?, ?, ?)',
            [$nombre, $tipo, $valor, $producto, $categoria, $activo]);
        if ($restricciones) {
            $campos = implode(', ', array_map(static fn (string $c): string => "{$c} = ?", array_keys($restricciones)));
            DB::consulta("UPDATE promociones SET {$campos} WHERE id = ?", [...array_values($restricciones), $id]);
        }
        return $id;
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

    /**
     * Escenario: Dos no acumulables en el mismo pedido (HU-50)
     *   Dado «Platos 10 %» y «Majadito a 34» (Bs 6 menos), ninguna acumulable
     *   Cuando cobro un Majadito y un Locro
     *   Entonces se aplica solo la de mayor beneficio: el 10 % a los dos platos (Bs 7,50)
     */
    public function testPorCategoriaGanaLaDeMayorBeneficio(): void
    {
        $platos = $this->promo('Platos 10 %', 'porcentaje', '10.00', null, $this->platos);
        $this->promo('Majadito a 34', 'monto', '6.00', $this->majadito);

        $this->assertSame(422, $this->cobrar([$this->majadito => 1, $this->locro => 1], '75.00', '9.50')->estado);
        $r = $this->cobrar([$this->majadito => 1, $this->locro => 1], '75.00', '7.50');

        $this->assertSame(200, $r->estado, $r->cuerpo);
        $usos = DB::filas('SELECT promocion_id, monto FROM venta_promociones ORDER BY monto DESC');
        $this->assertSame([[$platos, '4.00'], [$platos, '3.50']], array_map(static fn (array $u): array => [(int) $u['promocion_id'], $u['monto']], $usos));
        $this->assertSame('67.50', (string) DB::valor('SELECT total FROM ventas'));
    }

    /**
     * Escenario: Acumulables en el mismo pedido (HU-50)
     *   Dado «Platos 10 %» con prioridad 1 y «Majadito a 34» con prioridad 2, las dos acumulables
     *   Cuando cobro un Majadito
     *   Entonces se aplican las dos en ese orden: Bs 4 y luego Bs 6, y la línea guarda ambas
     */
    public function testLasAcumulablesSeSumanPorPrioridad(): void
    {
        $platos = $this->promo('Platos 10 %', 'porcentaje', '10.00', null, $this->platos, 1, ['acumulable' => 1, 'prioridad' => 1]);
        $majadito = $this->promo('Majadito a 34', 'monto', '6.00', $this->majadito, null, 1, ['acumulable' => 1, 'prioridad' => 2]);

        $r = $this->cobrar([$this->majadito => 1], '40.00', '10.00');

        $this->assertSame(200, $r->estado, $r->cuerpo);
        $f = Factura::buscar((int) json_decode($r->cuerpo, true)['id']);
        $this->assertSame(['10.00', '30.00'], array_values(DB::fila('SELECT descuento, subtotal FROM detalle_venta WHERE venta_id = ?', [$f['venta_id']])));
        $usos = DB::filas('SELECT promocion_id, monto FROM venta_promociones ORDER BY id');
        $this->assertSame([[$platos, '4.00'], [$majadito, '6.00']], array_map(static fn (array $u): array => [(int) $u['promocion_id'], $u['monto']], $usos));
        $html = $this->get('/facturas/' . $f['id'])->cuerpo;
        $this->assertStringContainsString('<li class="ticket__promo"><span>Platos 10 %</span><span class="num">−4,00</span></li><li class="ticket__promo"><span>Majadito a 34</span><span class="num">−6,00</span></li>', $html);
        $this->assertStringContainsString('1,00 × 40,00 − 10,00</span><span class="ticket__sub num">30,00</span>', $html);
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
     * Escenario: Fuera de horario no se aplica
     *   Dado «Happy hour», 10 % al Majadito de 18:00 a 21:00
     *   Cuando cobro a las 12:00
     *   Entonces no hay descuento; si la pantalla lo mostrara, el cobro se rechaza
     */
    public function testFueraDeHorarioNoSeAplica(): void
    {
        $this->promo('Happy hour', 'porcentaje', '10.00', $this->majadito, null, 1, ['hora_desde' => '18:00:00', 'hora_hasta' => '21:00:00']);

        $this->assertSame(422, $this->cobrar([$this->majadito => 2], '80.00', '8.00')->estado);
        $r = $this->cobrar([$this->majadito => 2], '80.00', '0.00');

        $this->assertSame(200, $r->estado, $r->cuerpo);
        $this->assertSame('80.00', (string) DB::valor('SELECT total FROM ventas'));

        Reloj::fijar('2026-10-22 18:30:00');
        $this->assertSame(200, $this->cobrar([$this->majadito => 2], '80.00', '8.00')->estado);
    }

    public function testOtroDiaOFueraDeFechasNoSeAplica(): void
    {
        $this->promo('Fin de semana', 'porcentaje', '10.00', $this->majadito, null, 1, ['dias' => 96]);
        $this->promo('Noviembre', 'porcentaje', '20.00', $this->majadito, null, 1, ['fecha_desde' => '2026-11-01']);

        $r = $this->cobrar([$this->majadito => 2], '80.00', '0.00');

        $this->assertSame(200, $r->estado, $r->cuerpo);
        $this->assertSame(0, (int) DB::valor('SELECT COUNT(*) FROM venta_promociones'));
    }

    /**
     * Escenario: Tope de usos por día
     *   Dado «Primeros 2», Bs 5 al Majadito con tope de 2 ventas por día
     *   Cuando ya se aplicó en 2 ventas hoy
     *   Entonces la tercera venta se cobra sin descuento; una venta anulada no cuenta, y al día siguiente vuelve
     */
    public function testElTopeDeUsosSeCuentaPorVentaYPorDia(): void
    {
        $this->promo('Primeros 2', 'monto', '5.00', $this->majadito, null, 1, ['tope_usos_dia' => 2]);

        $this->assertSame(200, $this->cobrar([$this->majadito => 2], '80.00', '10.00')->estado);
        $this->assertSame(200, $this->cobrar([$this->majadito => 1], '40.00', '5.00')->estado);
        $this->assertSame(422, $this->cobrar([$this->majadito => 1], '40.00', '5.00')->estado);
        $this->assertSame(200, $this->cobrar([$this->majadito => 1], '40.00', '0.00')->estado);

        // Una venta anulada libera su uso
        DB::consulta("UPDATE ventas SET estado = 'anulada' WHERE id = (SELECT MIN(venta_id) FROM venta_promociones)");
        $this->assertSame(200, $this->cobrar([$this->majadito => 1], '40.00', '5.00')->estado);

        $id = (int) DB::valor('SELECT id FROM promociones');
        $this->assertSame([$id => 2], \App\Models\Promocion::usosDelDia('2026-10-22'));

        // Al día siguiente el tope vuelve a empezar
        Reloj::fijar('2026-10-23 12:00:00');
        $this->assertSame([], \App\Models\Promocion::usosDelDia('2026-10-23'));
        $this->assertSame(200, $this->cobrar([$this->majadito => 1], '40.00', '5.00')->estado);
    }

    /**
     * Escenario: Condiciones del pedido
     *   Dado 10 % al Majadito desde 2 unidades y con pedidos desde Bs 90
     *   Cuando el pedido tiene 2 Majaditos (Bs 80) no se aplica; con un Mocochinchi más (Bs 90), sí
     */
    public function testCantidadYMontoMinimoAlCobrar(): void
    {
        $this->promo('Combo grande', 'porcentaje', '10.00', $this->majadito, null, 1, ['cantidad_minima' => 2, 'monto_minimo' => '90.00']);

        $this->assertSame(422, $this->cobrar([$this->majadito => 2], '80.00', '8.00')->estado);
        $this->assertSame(200, $this->cobrar([$this->majadito => 2], '80.00', '0.00')->estado);
        $this->assertSame(200, $this->cobrar([$this->majadito => 2, $this->moco => 1], '90.00', '8.00')->estado);
    }

    public function testElPuntoDeVentaRecibeLaHoraYLosUsosDeHoy(): void
    {
        $id = $this->promo('Primeros 2', 'monto', '5.00', $this->majadito, null, 1, ['tope_usos_dia' => 2]);
        $noche = $this->promo('Noche', 'monto', '3.00', $this->locro, null, 1, ['hora_desde' => '18:00:00', 'hora_hasta' => '24:00:00']);
        $this->cobrar([$this->majadito => 1], '40.00', '5.00');

        $html = $this->get('/pos')->cuerpo;

        $this->assertStringContainsString('data-ahora="2026-10-22T12:00:00"', $html);
        preg_match('/data-promociones="([^"]+)"/', $html, $m);
        $promos = array_column(json_decode(html_entity_decode($m[1]), true), null, 'id');
        $this->assertSame([1, 2], [$promos[$id]['usos'], $promos[$id]['tope_usos_dia']]);
        $this->assertSame(['18:00:00', '24:00:00'], [$promos[$noche]['hora_desde'], $promos[$noche]['hora_hasta']]);
        // El Locro tiene promoción, pero no a esta hora: su marca empieza oculta
        $this->assertMatchesRegularExpression('/data-id="' . $this->locro . '" data-promos="' . $noche . '".*?<span class="insignia insignia--promo" hidden>Promo<\/span>/s', $html);
    }

    private function regalo(string $nombre, int $compra, int $regalo, int $cada = 1, array $restricciones = []): int
    {
        $id = DB::insertar("INSERT INTO promociones (nombre, tipo, producto_id, cantidad_compra, regalo_producto_id) VALUES (?, 'regalo', ?, ?, ?)",
            [$nombre, $compra, $cada, $regalo]);
        if ($restricciones) {
            $campos = implode(', ', array_map(static fn (string $c): string => "{$c} = ?", array_keys($restricciones)));
            DB::consulta("UPDATE promociones SET {$campos} WHERE id = ?", [...array_values($restricciones), $id]);
        }
        return $id;
    }

    /**
     * Escenario: Regalo por compra (Picante + soda)
     *   Dado hoy es martes y la promoción «Promo martes» está activa: por cada Picante de pollo, 1 Soda cola 500 ml
     *   Cuando el cajero agrega 1 picante de pollo al carrito y cobra
     *   Entonces se agrega 1 soda personal a 0 Bs con la etiqueta «Promo martes»
     *   Y otro día de la semana la promoción no se aplica
     */
    public function testPicanteMasSodaLosMartes(): void
    {
        $picante = (int) DB::valor("SELECT id FROM productos WHERE codigo = 'PL-03'");   // Bs 45
        $soda = (int) DB::valor("SELECT id FROM productos WHERE codigo = 'SO-01'");      // Bs 8
        $promo = $this->regalo('Promo martes', $picante, $soda, 1, ['dias' => 2]);

        // Jueves 22/10: no aplica
        $this->assertSame(422, $this->cobrar([$picante => 1], '45.00', '0.00', ['regalos' => '8.00'])->estado);
        $this->assertSame(200, $this->cobrar([$picante => 1], '45.00', '0.00', ['regalos' => '0.00'])->estado);

        // Martes 27/10: la soda se agrega a Bs 0 con la etiqueta de la promoción
        Reloj::fijar('2026-10-27 12:00:00');
        $r = $this->cobrar([$picante => 1], '45.00', '0.00', ['regalos' => '8.00']);

        $this->assertSame(200, $r->estado, $r->cuerpo);
        $f = Factura::buscar((int) json_decode($r->cuerpo, true)['id']);
        $this->assertSame('45.00', (string) $f['monto_total']);
        $linea = DB::fila('SELECT id, cantidad, precio_unitario, descuento, subtotal FROM detalle_venta WHERE venta_id = ? AND producto_id = ?', [$f['venta_id'], $soda]);
        $this->assertSame([1, '8.00', '8.00', '0.00'], [(int) $linea['cantidad'], $linea['precio_unitario'], $linea['descuento'], $linea['subtotal']]);
        $uso = DB::fila('SELECT promocion_id, detalle_id, etiqueta, monto FROM venta_promociones WHERE venta_id = ?', [$f['venta_id']]);
        $this->assertSame([$promo, (int) $linea['id'], 'Promo martes', '8.00'], [(int) $uso['promocion_id'], (int) $uso['detalle_id'], $uso['etiqueta'], $uso['monto']]);

        $html = $this->get('/facturas/' . $f['id'])->cuerpo;
        $this->assertStringContainsString('<li><span>1 × Soda cola 500 ml</span><span class="num">8,00</span></li><li class="ticket__promo"><span>Promo martes</span><span class="num">−8,00</span></li>', $html);
        $this->assertStringContainsString('1,00 × 8,00 − 8,00</span><span class="ticket__sub num">0,00</span>', $html);
    }

    public function testElRegaloRespetaElTopeYLaCantidad(): void
    {
        $picante = (int) DB::valor("SELECT id FROM productos WHERE codigo = 'PL-03'");
        $soda = (int) DB::valor("SELECT id FROM productos WHERE codigo = 'SO-01'");
        $this->regalo('Dos picantes, una soda', $picante, $soda, 2, ['tope_usos_dia' => 1]);

        $this->assertSame(200, $this->cobrar([$picante => 1], '45.00', '0.00', ['regalos' => '0.00'])->estado);   // falta un picante
        $this->assertSame(200, $this->cobrar([$picante => 4], '180.00', '0.00', ['regalos' => '16.00'])->estado); // 2 sodas
        $this->assertSame(200, $this->cobrar([$picante => 2], '90.00', '0.00', ['regalos' => '0.00'])->estado);   // tope de hoy alcanzado
    }

    public function testUnRegaloAgotadoNoSeAgrega(): void
    {
        $picante = (int) DB::valor("SELECT id FROM productos WHERE codigo = 'PL-03'");
        $soda = (int) DB::valor("SELECT id FROM productos WHERE codigo = 'SO-01'");
        $this->regalo('Promo martes', $picante, $soda);
        DB::consulta('UPDATE productos SET agotado = 1 WHERE id = ?', [$soda]);

        $r = $this->cobrar([$picante => 2], '90.00', '0.00', ['regalos' => '0.00']);

        $this->assertSame(200, $r->estado, $r->cuerpo);
        $this->assertSame(1, (int) DB::valor('SELECT COUNT(*) FROM detalle_venta'));
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
        $this->assertSame([['id' => $activa, 'nombre' => 'Martes de majadito', 'tipo' => 'descuento', 'valor_tipo' => 'porcentaje', 'valor' => 1250, 'producto_id' => $this->majadito, 'categoria_id' => null,
            'cantidad_compra' => null, 'regalo_producto_id' => null, 'precio_combo' => null, 'items' => [], 'fecha_desde' => null, 'fecha_hasta' => null, 'dias' => 127, 'hora_desde' => null, 'hora_hasta' => null,
            'cantidad_minima' => null, 'monto_minimo' => null, 'tope_usos_dia' => null, 'acumulable' => false, 'prioridad' => 10, 'usos' => 0]], $promos);
        $this->assertMatchesRegularExpression('/data-id="' . $this->majadito . '" data-promos="' . $activa . '" aria-label="Majadito de charque, 40,00 bolivianos">.*?<span class="insignia insignia--promo">Promo<\/span>/s', $html);
        $this->assertMatchesRegularExpression('/data-id="' . $this->locro . '" aria-label="Locro carretero, 35,00 bolivianos">/', $html);
        $this->assertStringContainsString('<script src="/assets/js/motor-promociones.js"></script>', $html);
    }
}
