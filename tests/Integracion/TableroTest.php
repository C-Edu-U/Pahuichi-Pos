<?php

declare(strict_types=1);

namespace Tests\Integracion;

use App\Core\DB;
use App\Core\Reloj;
use Tests\CasoPrueba;

/**
 * HU-64: tablero del día (RF-60). Ventas acumuladas, ticket promedio, comparación con
 * ayer, más vendidos, QR por confirmar y avisos para el administrador; el cajero ve su
 * turno sin montos (cierre ciego).
 */
final class TableroTest extends CasoPrueba
{
    private int $majadito;   // Bs 40
    private int $locro;      // Bs 35

    protected function setUp(): void
    {
        parent::setUp();
        $this->majadito = (int) DB::valor("SELECT id FROM productos WHERE codigo = 'PL-01'");
        $this->locro = (int) DB::valor("SELECT id FROM productos WHERE codigo = 'PL-02'");
        // El CUFD de prueba vale hoy, para que su aviso no se mezcle con los demás
        DB::consulta("UPDATE parametros_fiscales SET cufd_vigencia = '2026-10-23 23:59:00' WHERE id = 1");
    }

    /** Cobra como Mariela a la hora indicada; abre su caja si hace falta. */
    private function vender(string $momento, array $cantidades, string $total): int
    {
        Reloj::fijar($momento);
        $this->entrarComo('mariela', 'pin');
        if (DB::valor("SELECT id FROM cajas WHERE estado = 'abierta'") === null) {
            $this->post('/caja/abrir', ['turno_id' => (int) DB::valor("SELECT id FROM turnos WHERE clave = 'mediodia'"), 'fondo' => '200']);
        }
        $lineas = [];
        foreach ($cantidades as $id => $n) {
            $lineas[] = ['id' => $id, 'cantidad' => $n, 'nota' => ''];
        }
        $r = $this->post('/pos/cobrar', [
            'uuid' => '12345678-1234-4123-a123-' . bin2hex(random_bytes(6)),
            'caja_id' => (string) DB::valor("SELECT id FROM cajas WHERE estado = 'abierta'"), 'tipo' => 'llevar', 'mesa' => '', 'exacto' => '1',
            'total_esperado' => $total, 'lineas' => json_encode($lineas),
        ]);
        $this->assertSame(200, $r->estado, $r->cuerpo);
        return (int) DB::valor('SELECT MAX(id) FROM ventas');
    }

    private function tablero(): string
    {
        Reloj::fijar('2026-10-23 12:00:00');
        $this->entrarComo('admin');
        $r = $this->get('/tablero');
        $this->assertSame(200, $r->estado);
        return $r->cuerpo;
    }

    /**
     * Escenario: Ventas del día
     *   Dado hoy hubo 2 ventas por Bs 115 y ayer, hasta esta hora, una de Bs 50
     *   Cuando el administrador abre el tablero
     *   Entonces ve Bs 115, 2 ventas, ticket promedio Bs 57,50 y +130 % frente a ayer
     */
    public function testLasVentasDelDiaYLaComparacionConAyer(): void
    {
        $this->vender('2026-10-22 11:00:00', [$this->majadito => 1, $this->locro => 1], '75.00');
        $this->vender('2026-10-22 18:00:00', [$this->locro => 1], '35.00');   // después de esta hora: no cuenta para la comparación
        DB::consulta("UPDATE ventas SET total = 50.00, subtotal = 50.00 WHERE id = (SELECT MIN(id) FROM (SELECT id FROM ventas) v)");
        DB::consulta("UPDATE pagos SET monto = 50.00, recibido = 50.00 WHERE venta_id = (SELECT MIN(id) FROM (SELECT id FROM ventas) v)");
        $this->vender('2026-10-23 11:30:00', [$this->majadito => 2], '80.00');
        $this->vender('2026-10-23 11:45:00', [$this->locro => 1], '35.00');

        $html = $this->tablero();

        $this->assertStringContainsString('<p class="hoy__valor num" id="ventas-hoy"><span class="cifra__moneda">Bs</span>115,00</p>', $html);
        $this->assertStringContainsString('<dd class="num" id="ventas-cantidad">2</dd>', $html);
        $this->assertStringContainsString('<dd class="num">Bs 57,50</dd>', $html);
        $this->assertStringContainsString('tablero-dia__variacion--sube">+130,0 %</span>', $html);
    }

    public function testSinVentasNoHayConQueComparar(): void
    {
        $html = $this->tablero();

        $this->assertStringContainsString('<span class="cifra__moneda">Bs</span>0,00', $html);
        $this->assertStringContainsString('Sin ventas ayer a esta hora', $html);
        $this->assertStringContainsString('Todavía no hay ventas hoy.', $html);
        $this->assertStringContainsString('No hay una caja abierta.', $html);
    }

    /**
     * Escenario: Más vendidos
     *   Dado hoy se vendieron 3 Majaditos y 1 Locro, y un Locro de una venta anulada
     *   Cuando el administrador abre el tablero
     *   Entonces los más vendidos son Majadito (3) y Locro (1); lo anulado no cuenta
     */
    public function testLosMasVendidosSinAnuladasNiRegalos(): void
    {
        $soda = (int) DB::valor("SELECT id FROM productos WHERE codigo = 'SO-01'");
        DB::insertar("INSERT INTO promociones (nombre, tipo, producto_id, cantidad_compra, regalo_producto_id) VALUES ('Soda de regalo', 'regalo', ?, 1, ?)", [$this->majadito, $soda]);
        Reloj::fijar('2026-10-23 11:00:00');
        $this->entrarComo('mariela', 'pin');
        $this->post('/caja/abrir', ['turno_id' => (int) DB::valor("SELECT id FROM turnos WHERE clave = 'mediodia'"), 'fondo' => '200']);
        $caja = (string) DB::valor("SELECT id FROM cajas WHERE estado = 'abierta'");
        $cobrar = fn (array $lineas, string $total, string $regalos): int => $this->post('/pos/cobrar', ['uuid' => '12345678-1234-4123-a123-' . bin2hex(random_bytes(6)),
            'caja_id' => $caja, 'tipo' => 'llevar', 'mesa' => '', 'exacto' => '1', 'total_esperado' => $total, 'regalos' => $regalos, 'lineas' => json_encode($lineas)])->estado;
        $this->assertSame(200, $cobrar([['id' => $this->majadito, 'cantidad' => 3, 'nota' => ''], ['id' => $this->locro, 'cantidad' => 1, 'nota' => '']], '155.00', '24.00'));
        $this->assertSame(200, $cobrar([['id' => $this->locro, 'cantidad' => 2, 'nota' => '']], '70.00', '0.00'));
        DB::consulta("UPDATE ventas SET estado = 'anulada' WHERE id = (SELECT MAX(id) FROM (SELECT id FROM ventas) v)");

        $html = $this->tablero();

        $this->assertSame(1, preg_match('/<ol class="ranking" role="list" id="mas-vendidos">(.*?)<\/ol>/s', $html, $m));
        $this->assertSame(['Majadito de charque 3', 'Locro carretero 1'], array_map(
            static fn (string $li): string => trim((string) preg_replace('/\s+/', ' ', strip_tags(str_replace('</span><span', '</span> <span', $li)))),
            array_values(array_filter(explode('</li>', $m[1]), static fn (string $x): bool => trim($x) !== '')),
        ));
        $this->assertStringContainsString('<span class="cifra__moneda">Bs</span>155,00', $html);
    }

    /**
     * Escenario: Para atender
     *   Dado un usuario bloqueado, un producto agotado, el CUFD vencido y la caja de ayer abierta
     *   Cuando el administrador abre el tablero
     *   Entonces ve cada aviso con un enlace para resolverlo
     */
    public function testLosAvisosParaAtender(): void
    {
        $this->vender('2026-10-22 12:00:00', [$this->majadito => 1], '40.00');   // la caja de ayer queda abierta
        DB::consulta("UPDATE usuarios SET bloqueado_hasta = '2026-10-23 12:10:00' WHERE usuario = 'jorge'");
        DB::consulta("UPDATE parametros_fiscales SET cufd_vigencia = '2026-10-22 23:59:00' WHERE id = 1");

        $html = $this->tablero();

        $this->assertStringContainsString('La caja de medio día del 22/10 sigue abierta', $html);
        $this->assertStringContainsString('El CUFD no corresponde a hoy', $html);
        $this->assertStringContainsString('Jorge Rivero Chávez está bloqueado hasta las 12:10', $html);
        $this->assertStringContainsString('Fricasé de cerdo está agotado', $html);   // agotado en los datos de prueba
        $this->assertStringContainsString('href="/config#fiscal"', $html);
    }

    public function testSinAvisosDiceQueTodoEstaEnOrden(): void
    {
        DB::consulta('UPDATE productos SET agotado = 0');

        $this->assertStringContainsString('Todo en orden.', $this->tablero());
    }

    /**
     * Escenario: El cajero ve su turno sin montos
     *   Dado Mariela tiene la caja abierta y cobró 2 ventas
     *   Cuando abre el tablero
     *   Entonces ve su turno y la cantidad de ventas, pero ningún monto
     */
    public function testElCajeroVeSuTurnoSinMontos(): void
    {
        $this->vender('2026-10-23 11:30:00', [$this->majadito => 2], '80.00');
        $this->vender('2026-10-23 11:40:00', [$this->locro => 1], '35.00');

        $html = $this->get('/tablero')->cuerpo;

        $this->assertStringContainsString('<dd class="num" id="ventas-cantidad">2</dd>', $html);
        $this->assertStringContainsString('Ir al punto de venta', $html);
        $this->assertSame(1, preg_match('/<div id="tablero"[^>]*>(.*?)<\/main>/s', $html, $m));
        $this->assertStringNotContainsString('Bs', $m[1]);
        $this->assertStringNotContainsString('115', $m[1]);

        $this->entrarComo('jorge', 'pin');
        $this->assertStringContainsString('La caja abierta es de Mariela Suárez Añez.', $this->get('/tablero')->cuerpo);
    }

    public function testElMeseroNoEntra(): void
    {
        $this->entrarComo('luis');

        $this->assertSame(403, $this->get('/tablero')->estado);
    }
}
