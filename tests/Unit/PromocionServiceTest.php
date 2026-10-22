<?php

declare(strict_types=1);
namespace Tests\Unit;

use App\Services\PromocionService;
use PHPUnit\Framework\TestCase;

/**
 * HU-46: motor de promociones. Montos en centavos; el valor de la promoción como
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
