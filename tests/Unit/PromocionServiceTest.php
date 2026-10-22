<?php

declare(strict_types=1);
namespace Tests\Unit;

use App\Services\PromocionService;
use PHPUnit\Framework\TestCase;

/**
 * HU-46 y HU-49: motor de promociones. Montos en centavos; el valor de la promoción como
 * lo guarda la base (DECIMAL con dos decimales). public/assets/js/motor-promociones.js
 * repite este cálculo y debe dar lo mismo.
 */
final class PromocionServiceTest extends TestCase
{
    private const MAJADITO = ['producto_id' => 1, 'categoria_id' => 1];
    private const LOCRO = ['producto_id' => 2, 'categoria_id' => 1];
    private const MOCOCHINCHI = ['producto_id' => 10, 'categoria_id' => 2];

    private static function linea(array $producto, int $cantidad, int $precio): array
    {
        return $producto + ['cantidad' => $cantidad, 'bruto' => $precio * $cantidad];
    }

    private static function promo(int $id, string $tipo, string $valor, ?int $producto = null, ?int $categoria = null): array
    {
        return ['id' => $id, 'nombre' => 'Promo ' . $id, 'valor_tipo' => $tipo, 'valor' => $valor, 'producto_id' => $producto, 'categoria_id' => $categoria];
    }

    /**
     * Escenario: Descuento en porcentaje por producto
     *   Dado una promoción de 10 % al Majadito (Bs 40)
     *   Cuando agrego 2 Majaditos
     *   Entonces la línea tiene un descuento de Bs 8
     */
    public function testPorcentajeSobreLaLinea(): void
    {
        $r = PromocionService::aplicar([self::linea(self::MAJADITO, 2, 4000)], [self::promo(1, 'porcentaje', '10.00', 1)]);

        $this->assertSame(800, $r[0]['descuento']);
        $this->assertSame(1, $r[0]['promocion']['id']);
    }

    public function testElPorcentajeRedondeaAlCentavo(): void
    {
        // 12,5 % de Bs 35,00 = 4,375 → 4,38
        $r = PromocionService::aplicar([self::linea(self::LOCRO, 1, 3500)], [self::promo(1, 'porcentaje', '12.50', 2)]);

        $this->assertSame(438, $r[0]['descuento']);
    }

    /**
     * Escenario: Descuento en monto fijo por unidad
     *   Dado una promoción de Bs 5 por unidad al Majadito
     *   Cuando agrego 3 Majaditos
     *   Entonces la línea tiene un descuento de Bs 15
     */
    public function testMontoFijoPorUnidad(): void
    {
        $r = PromocionService::aplicar([self::linea(self::MAJADITO, 3, 4000)], [self::promo(1, 'monto', '5.00', 1)]);

        $this->assertSame(1500, $r[0]['descuento']);
    }

    /**
     * Escenario: Descuento por categoría
     *   Dado una promoción de 20 % a la categoría Platos
     *   Cuando el pedido tiene un Majadito, un Locro y un Mocochinchi
     *   Entonces descuentan los dos platos y la bebida no
     */
    public function testPorCategoria(): void
    {
        $r = PromocionService::aplicar(
            [self::linea(self::MAJADITO, 1, 4000), self::linea(self::LOCRO, 1, 3500), self::linea(self::MOCOCHINCHI, 1, 1000)],
            [self::promo(1, 'porcentaje', '20.00', null, 1)],
        );

        $this->assertSame([800, 700, 0], array_column($r, 'descuento'));
        $this->assertNull($r[2]['promocion']);
    }

    /**
     * Escenario: Ninguna línea queda en negativo
     *   Dado un descuento de Bs 50 por unidad sobre un producto de Bs 40
     *   Cuando se aplica
     *   Entonces descuenta como máximo los Bs 40 de la línea
     */
    public function testNuncaDejaLaLineaEnNegativo(): void
    {
        $r = PromocionService::aplicar([self::linea(self::MAJADITO, 1, 4000)], [self::promo(1, 'monto', '50.00', null, 1)]);

        $this->assertSame(4000, $r[0]['descuento']);
    }

    /**
     * Escenario: Dos promociones sobre el mismo producto
     *   Dado 10 % a la categoría Platos y Bs 6 por unidad al Majadito
     *   Cuando agrego un Majadito de Bs 40
     *   Entonces se aplica solo la de mayor beneficio (Bs 6)
     */
    public function testGanaLaDeMayorBeneficio(): void
    {
        $r = PromocionService::aplicar(
            [self::linea(self::MAJADITO, 1, 4000), self::linea(self::LOCRO, 1, 3500)],
            [self::promo(1, 'porcentaje', '10.00', null, 1), self::promo(2, 'monto', '6.00', 1)],
        );

        $this->assertSame(600, $r[0]['descuento']);
        $this->assertSame(2, $r[0]['promocion']['id']);
        $this->assertSame(350, $r[1]['descuento']);   // el Locro solo tiene la de categoría
        $this->assertSame(1, $r[1]['promocion']['id']);
    }

    public function testAIgualBeneficioGanaLaMasAntigua(): void
    {
        $r = PromocionService::aplicar([self::linea(self::MAJADITO, 1, 4000)], [self::promo(3, 'monto', '4.00', 1), self::promo(7, 'porcentaje', '10.00', 1)]);

        $this->assertSame(3, $r[0]['promocion']['id']);
    }

    public function testSinPromocionesNoHayDescuento(): void
    {
        $r = PromocionService::aplicar([self::linea(self::MAJADITO, 2, 4000)], []);

        $this->assertSame([['descuento' => 0, 'promocion' => null]], $r);
    }

    /* ------------------------------------------------------------------
       HU-49: restricciones combinables. El contexto por defecto es el jueves
       22/10/2026 a las 12:30, sin usos en el día.
       ------------------------------------------------------------------ */

    private static function ctx(string $fecha = '2026-10-22', int $dia = 4, string $hora = '12:30:00', array $usos = []): array
    {
        return ['fecha' => $fecha, 'dia' => $dia, 'hora' => $hora, 'usos' => $usos];
    }

    /** Descuento del Majadito (2 × Bs 40 si no se dan líneas) con 10 % y las restricciones dadas. */
    private static function conRestricciones(array $restricciones, array $ctx, array $lineas = []): int
    {
        $p = $restricciones + self::promo(1, 'porcentaje', '10.00', 1);
        return PromocionService::aplicar($lineas ?: [self::linea(self::MAJADITO, 2, 4000)], [$p], $ctx)[0]['descuento'];
    }

    /**
     * Escenario: Rango de fechas
     *   Dado una promoción del 01/11 al 30/11
     *   Cuando vendo el 22/10 o el 01/12
     *   Entonces no se aplica; el 01/11 y el 30/11 sí (ambos se incluyen)
     */
    public function testRangoDeFechas(): void
    {
        $noviembre = ['fecha_desde' => '2026-11-01', 'fecha_hasta' => '2026-11-30'];

        $this->assertSame(0, self::conRestricciones($noviembre, self::ctx('2026-10-22')));
        $this->assertSame(0, self::conRestricciones($noviembre, self::ctx('2026-12-01', 2)));
        $this->assertSame(800, self::conRestricciones($noviembre, self::ctx('2026-11-01', 7)));
        $this->assertSame(800, self::conRestricciones($noviembre, self::ctx('2026-11-30', 1)));
        $this->assertSame('programada', PromocionService::motivoFueraDeVigencia($noviembre + ['id' => 1], self::ctx()));
        $this->assertSame('vencida', PromocionService::motivoFueraDeVigencia(['id' => 1, 'fecha_hasta' => '2026-10-21'], self::ctx()));
    }

    /**
     * Escenario: Días de la semana
     *   Dado una promoción de lunes a viernes
     *   Cuando vendo un jueves se aplica, y un sábado no
     */
    public function testDiasDeLaSemana(): void
    {
        $habiles = ['dias' => 31];   // lunes (1) a viernes (16)

        $this->assertSame(800, self::conRestricciones($habiles, self::ctx('2026-10-22', 4)));
        $this->assertSame(0, self::conRestricciones($habiles, self::ctx('2026-10-24', 6)));
        $this->assertSame(0, self::conRestricciones(['dias' => 64], self::ctx()));   // solo domingo
    }

    /**
     * Escenario: Franja horaria
     *   Dado una promoción de 12:00 a 15:00
     *   Cuando vendo a las 12:00 o a las 14:59 se aplica; a las 11:59 o a las 15:00, no
     */
    public function testFranjaHoraria(): void
    {
        $almuerzo = ['hora_desde' => '12:00:00', 'hora_hasta' => '15:00:00'];

        $this->assertSame(800, self::conRestricciones($almuerzo, self::ctx(hora: '12:00:00')));
        $this->assertSame(800, self::conRestricciones($almuerzo, self::ctx(hora: '14:59:59')));
        $this->assertSame(0, self::conRestricciones($almuerzo, self::ctx(hora: '11:59:59')));
        $this->assertSame(0, self::conRestricciones($almuerzo, self::ctx(hora: '15:00:00')));
        // Hasta medianoche
        $this->assertSame(800, self::conRestricciones(['hora_desde' => '18:00:00', 'hora_hasta' => '24:00:00'], self::ctx(hora: '23:59:59')));
    }

    /**
     * Escenario: Cantidad mínima
     *   Dado 10 % a la categoría Platos desde 3 unidades
     *   Cuando el pedido tiene 2 Majaditos y 1 Locro (3 platos)
     *   Entonces se aplica a los dos; con 2 platos, no
     */
    public function testCantidadMinimaSumaLasUnidadesDelProductoOLaCategoria(): void
    {
        $platos = ['cantidad_minima' => 3] + self::promo(1, 'porcentaje', '10.00', null, 1);

        $tres = PromocionService::aplicar([self::linea(self::MAJADITO, 2, 4000), self::linea(self::LOCRO, 1, 3500), self::linea(self::MOCOCHINCHI, 5, 1000)], [$platos], self::ctx());
        $dos = PromocionService::aplicar([self::linea(self::MAJADITO, 1, 4000), self::linea(self::LOCRO, 1, 3500), self::linea(self::MOCOCHINCHI, 5, 1000)], [$platos], self::ctx());

        $this->assertSame([800, 350, 0], array_column($tres, 'descuento'));
        $this->assertSame([0, 0, 0], array_column($dos, 'descuento'));
    }

    /**
     * Escenario: Monto mínimo del pedido
     *   Dado 10 % al Majadito con pedidos desde Bs 100
     *   Cuando el pedido suma Bs 100 entre todos sus productos se aplica; con Bs 90, no
     */
    public function testMontoMinimoMiraTodoElPedido(): void
    {
        $desde100 = ['monto_minimo' => '100.00'];

        $this->assertSame(800, self::conRestricciones($desde100, self::ctx(), [self::linea(self::MAJADITO, 2, 4000), self::linea(self::MOCOCHINCHI, 2, 1000)]));
        $this->assertSame(0, self::conRestricciones($desde100, self::ctx(), [self::linea(self::MAJADITO, 2, 4000), self::linea(self::MOCOCHINCHI, 1, 1000)]));
    }

    /**
     * Escenario: Tope de usos por día
     *   Dado una promoción con tope de 2 ventas por día
     *   Cuando ya se aplicó en 2 ventas hoy
     *   Entonces no se aplica a la siguiente
     */
    public function testTopeDeUsosPorDia(): void
    {
        $tope = ['tope_usos_dia' => 2];

        $this->assertSame(800, self::conRestricciones($tope, self::ctx(usos: [1 => 1])));
        $this->assertSame(0, self::conRestricciones($tope, self::ctx(usos: [1 => 2])));
        $this->assertSame('tope', PromocionService::motivoFueraDeVigencia($tope + ['id' => 1], self::ctx(usos: [1 => 2])));
    }

    /**
     * Escenario: Restricciones combinadas
     *   Dado una promoción de lunes a viernes, de 12:00 a 15:00, desde 2 unidades
     *   Cuando falta cualquiera de las tres
     *   Entonces no se aplica
     */
    public function testLasRestriccionesSeCombinan(): void
    {
        $todas = ['dias' => 31, 'hora_desde' => '12:00:00', 'hora_hasta' => '15:00:00', 'cantidad_minima' => 2];

        $this->assertSame(800, self::conRestricciones($todas, self::ctx()));
        $this->assertSame(0, self::conRestricciones($todas, self::ctx('2026-10-24', 6)));
        $this->assertSame(0, self::conRestricciones($todas, self::ctx(hora: '16:00:00')));
        $this->assertSame(0, self::conRestricciones($todas, self::ctx(), [self::linea(self::MAJADITO, 1, 4000)]));
    }

    public function testSiLaMejorNoCumpleGanaLaQueSi(): void
    {
        $r = PromocionService::aplicar(
            [self::linea(self::MAJADITO, 1, 4000)],
            [self::promo(1, 'porcentaje', '10.00', null, 1), ['cantidad_minima' => 2] + self::promo(2, 'monto', '8.00', 1)],
            self::ctx(),
        );

        $this->assertSame([400, 1], [$r[0]['descuento'], $r[0]['promocion']['id']]);
    }

    public function testCuandoYCondicionesEnPalabras(): void
    {
        $this->assertSame('Todos los días', PromocionService::cuando([]));
        $this->assertSame('De lunes a viernes, 12:00 a 15:00 · del 01/11/2026 al 30/11/2026', PromocionService::cuando(['dias' => 31, 'hora_desde' => '12:00:00', 'hora_hasta' => '15:00:00', 'fecha_desde' => '2026-11-01', 'fecha_hasta' => '2026-11-30']));
        $this->assertSame('mar, jue · desde el 01/11/2026', PromocionService::cuando(['dias' => 10, 'fecha_desde' => '2026-11-01']));
        $this->assertSame('Sin condiciones', PromocionService::condiciones([]));
        $this->assertSame('Desde 2 unidades · pedido desde Bs 50,00 · tope 20 por día', PromocionService::condiciones(['cantidad_minima' => 2, 'monto_minimo' => '50.00', 'tope_usos_dia' => 20]));
    }

    private static function regalo(int $id, int $cada, int $regalo, ?int $producto = null, ?int $categoria = null): array
    {
        return ['id' => $id, 'nombre' => 'Regalo ' . $id, 'tipo' => 'regalo', 'valor_tipo' => null, 'valor' => null,
            'producto_id' => $producto, 'categoria_id' => $categoria, 'cantidad_compra' => $cada, 'regalo_producto_id' => $regalo];
    }

    /**
     * Escenario: Regalo por compra
     *   Dado «Picante + soda»: por cada picante, una soda gratis
     *   Cuando el pedido tiene 1 picante
     *   Entonces corresponde 1 soda de regalo
     */
    public function testRegaloPorCadaUnidadComprada(): void
    {
        $picante = ['producto_id' => 3, 'categoria_id' => 1];

        $uno = PromocionService::regalos([self::linea($picante, 1, 4500)], [self::regalo(1, 1, 58, 3)]);
        $tres = PromocionService::regalos([self::linea($picante, 3, 4500)], [self::regalo(1, 1, 58, 3)]);

        $this->assertSame([['promocion' => self::regalo(1, 1, 58, 3), 'producto_id' => 58, 'cantidad' => 1]], $uno);
        $this->assertSame(3, $tres[0]['cantidad']);
    }

    public function testRegaloPorCadaNUnidadesDeLaCategoria(): void
    {
        // Por cada 2 platos, un mocochinchi: 2 Majaditos y 1 Locro = 3 platos → 1 regalo
        $r = PromocionService::regalos(
            [self::linea(self::MAJADITO, 2, 4000), self::linea(self::LOCRO, 1, 3500), self::linea(self::MOCOCHINCHI, 4, 1000)],
            [self::regalo(1, 2, 10, null, 1)],
        );

        $this->assertSame(1, $r[0]['cantidad']);
        $this->assertSame([], PromocionService::regalos([self::linea(self::MAJADITO, 1, 4000)], [self::regalo(1, 2, 10, null, 1)]));
    }

    public function testElRegaloNoDescuentaYElDescuentoNoRegala(): void
    {
        $promos = [self::regalo(1, 1, 58, 1), self::promo(2, 'porcentaje', '10.00', 10)];
        $lineas = [self::linea(self::MAJADITO, 1, 4000), self::linea(self::MOCOCHINCHI, 1, 1000)];

        $this->assertSame([0, 100], array_column(PromocionService::aplicar($lineas, $promos), 'descuento'));
        $this->assertCount(1, PromocionService::regalos($lineas, $promos));
        $this->assertSame('1 Soda cola 500 ml gratis por cada uno', PromocionService::regla(['regalo' => 'Soda cola 500 ml'] + self::regalo(1, 1, 58, 3)));
        $this->assertSame('1 Mocochinchi gratis por cada 2', PromocionService::regla(['regalo' => 'Mocochinchi'] + self::regalo(1, 2, 10, 3)));
    }

    public function testLaReglaSeLeeEnPalabras(): void
    {
        $this->assertSame('10 % menos', PromocionService::regla(['valor_tipo' => 'porcentaje', 'valor' => '10.00']));
        $this->assertSame('12,5 % menos', PromocionService::regla(['valor_tipo' => 'porcentaje', 'valor' => '12.50']));
        $this->assertSame('Bs 5,00 menos por unidad', PromocionService::regla(['valor_tipo' => 'monto', 'valor' => '5.00']));
    }
}
