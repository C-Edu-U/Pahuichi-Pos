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
 * HU-47: combo de productos a precio especial (RF-49), «majadito + mocochinchi a Bs 45».
 * El cálculo del motor está en tests/promociones/casos.json; aquí, la administración,
 * el cobro y los tickets.
 */
final class CombosTest extends CasoPrueba
{
    private int $majadito;   // Bs 40
    private int $moco;       // Bs 10

    protected function setUp(): void
    {
        parent::setUp();
        Reloj::fijar('2026-10-23 12:00:00');
        $this->majadito = (int) DB::valor("SELECT id FROM productos WHERE codigo = 'PL-01'");
        $this->moco = (int) DB::valor("SELECT id FROM productos WHERE codigo = 'RE-01'");
        $this->entrarComo('admin');
    }

    private function crear(array $datos = []): Respuesta
    {
        return $this->post('/promociones/nueva', $datos + [
            'nombre' => 'Combo almuerzo', 'tipo' => 'combo', 'item_producto' => [(string) $this->majadito, (string) $this->moco, '', ''],
            'item_cantidad' => ['1', '1', '1', '1'], 'precio_combo' => '45', 'activo' => '1', 'dias' => ['1', '2', '3', '4', '5', '6', '7'],
        ]);
    }

    private function cobrar(array $cantidades, string $bruto, string $promociones): Respuesta
    {
        $lineas = [];
        foreach ($cantidades as $id => $n) {
            $lineas[] = ['id' => $id, 'cantidad' => $n, 'nota' => ''];
        }
        return $this->post('/pos/cobrar', [
            'uuid' => '12345678-1234-4123-a123-' . bin2hex(random_bytes(6)),
            'caja_id' => (string) DB::valor("SELECT id FROM cajas WHERE estado = 'abierta'"), 'tipo' => 'llevar', 'mesa' => '', 'exacto' => '1',
            'total_esperado' => $bruto, 'promociones' => $promociones, 'regalos' => '0.00', 'lineas' => json_encode($lineas),
        ]);
    }

    private function abrirCaja(): void
    {
        $this->entrarComo('mariela', 'pin');
        $this->post('/caja/abrir', ['turno_id' => (int) DB::valor("SELECT id FROM turnos WHERE clave = 'mediodia'"), 'fondo' => '200']);
    }

    public function testElAdministradorCreaElCombo(): void
    {
        $this->assertRedirige('/promociones', $this->crear());

        $p = DB::fila("SELECT id, tipo, precio_combo, producto_id, categoria_id, cantidad_minima FROM promociones WHERE nombre = 'Combo almuerzo'");
        $this->assertSame(['combo', '45.00', null, null, null], [$p['tipo'], $p['precio_combo'], $p['producto_id'], $p['categoria_id'], $p['cantidad_minima']]);
        $items = DB::filas('SELECT producto_id, cantidad FROM promocion_items WHERE promocion_id = ? ORDER BY producto_id', [$p['id']]);
        $this->assertSame([[$this->majadito, 1], [$this->moco, 1]], array_map(static fn (array $i): array => [(int) $i['producto_id'], (int) $i['cantidad']], $items));
        $lista = $this->get('/promociones')->cuerpo;
        $this->assertStringContainsString('Majadito de charque + Mocochinchi a Bs 45,00', $lista);
        $this->assertStringContainsString('Combo · No acumulable', $lista);
        $this->assertStringContainsString('"aplica":"Combo"', (string) Bitacora::ultimos('promocion.alta')[0]['detalle']);

        $form = $this->get('/promociones/' . $p['id'] . '/editar')->cuerpo;
        $this->assertStringContainsString('name="tipo" value="combo" checked', $form);
        $this->assertStringContainsString('value="45,00"', $form);
    }

    public static function invalidos(): array
    {
        return [
            'un solo producto' => [['item_producto' => ['1', '', '', '']], 'items', 'Un combo lleva al menos 2 productos distintos.'],
            'producto repetido' => [['item_producto' => ['1', '1', '', '']], 'items', 'Cada producto va una sola vez; usa la cantidad para repetirlo.'],
            'cantidad cero' => [['item_cantidad' => ['0', '1', '1', '1']], 'items', 'Cada producto lleva de 1 a 20 unidades.'],
            'sin precio' => [['precio_combo' => ''], 'precio_combo', 'Escribe el precio del combo, mayor que cero.'],
            'no ahorra' => [['precio_combo' => '50'], 'precio_combo', 'Debe ser menor que el precio por separado (Bs 50,00).'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidos')]
    public function testValidaElCombo(array $datos, string $campo, string $mensaje): void
    {
        if (isset($datos['item_producto'])) {
            $datos['item_producto'] = array_map(fn (string $x): string => $x === '1' ? (string) $this->majadito : $x, $datos['item_producto']);
        }
        $r = $this->crear($datos);

        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString(e($mensaje), $r->cuerpo);
        $this->assertStringContainsString('id="' . $campo . '"', $r->cuerpo);
        $this->assertSame(0, (int) DB::valor('SELECT COUNT(*) FROM promociones'));
    }

    /**
     * Escenario 1: Combo completo
     *   Dado el combo majadito + mocochinchi a 45 Bs
     *   Cuando ambos productos están en el carrito
     *   Entonces el precio del par pasa a 45 Bs
     * Escenario 3: Ahorro visible
     *   Dado un combo aplicado
     *   Cuando veo los tickets
     *   Entonces aparece una línea con el ahorro, repartido entre los dos productos en la factura
     */
    public function testElParPasaABs45YSeVeElAhorro(): void
    {
        $this->crear();
        $this->abrirCaja();

        $r = $this->cobrar([$this->majadito => 1, $this->moco => 1], '50.00', '5.00');

        $this->assertSame(200, $r->estado, $r->cuerpo);
        $f = Factura::buscar((int) json_decode($r->cuerpo, true)['id']);
        $this->assertSame('45.00', (string) $f['monto_total']);
        $lineas = DB::filas('SELECT producto_id, descuento, subtotal FROM detalle_venta WHERE venta_id = ? ORDER BY producto_id', [$f['venta_id']]);
        $this->assertSame([['4.00', '36.00'], ['1.00', '9.00']], array_map(static fn (array $l): array => [$l['descuento'], $l['subtotal']], $lineas));
        $html = $this->get('/facturas/' . $f['id'])->cuerpo;
        $this->assertStringContainsString('<li class="ticket__promo"><span>Combo almuerzo</span><span class="num">−4,00</span></li>', $html);
        $this->assertStringContainsString('1,00 × 10,00 − 1,00</span><span class="ticket__sub num">9,00</span>', $html);
    }

    /**
     * Escenario 2: Combo incompleto
     *   Dado solo el majadito en el carrito
     *   Cuando reviso el total
     *   Entonces se cobra el precio normal
     */
    public function testSinElMocochinchiSeCobraPrecioNormal(): void
    {
        $this->crear();
        $this->abrirCaja();

        $this->assertSame(422, $this->cobrar([$this->majadito => 1], '40.00', '5.00')->estado);
        $r = $this->cobrar([$this->majadito => 1], '40.00', '0.00');

        $this->assertSame(200, $r->estado, $r->cuerpo);
        $this->assertSame('40.00', (string) DB::valor('SELECT total FROM ventas'));
        $this->assertSame(0, (int) DB::valor('SELECT COUNT(*) FROM venta_promociones'));
    }

    /** Con otras promociones antes que el combo, la lista y el punto de venta cargan sus productos. */
    public function testElComboNoEsLaPrimeraPromocion(): void
    {
        DB::insertar("INSERT INTO promociones (nombre, valor_tipo, valor, producto_id) VALUES ('Antes', 'porcentaje', '10.00', ?)", [$this->majadito]);
        $this->crear();
        $this->abrirCaja();

        $this->assertSame(200, $this->get('/pos')->estado);
        $this->entrarComo('admin');
        $this->assertStringContainsString('Majadito de charque + Mocochinchi a Bs 45,00', $this->get('/promociones')->cuerpo);
    }

    public function testElPuntoDeVentaRecibeLosProductosDelCombo(): void
    {
        $this->crear();
        $this->abrirCaja();

        $html = $this->get('/pos')->cuerpo;

        preg_match('/data-promociones="([^"]+)"/', $html, $m);
        $combo = json_decode(html_entity_decode($m[1]), true)[0];
        $this->assertSame(['combo', 4500, [['producto_id' => $this->majadito, 'cantidad' => 1], ['producto_id' => $this->moco, 'cantidad' => 1]]],
            [$combo['tipo'], $combo['precio_combo'], $combo['items']]);
        // Los dos productos del combo llevan la marca «Promo»
        $this->assertMatchesRegularExpression('/data-id="' . $this->moco . '" data-promos="' . $combo['id'] . '"/', $html);
    }
}
