<?php

declare(strict_types=1);

namespace Tests\Integracion;

use App\Core\DB;
use App\Core\Reloj;
use App\Core\Respuesta;
use Tests\CasoPrueba;

/**
 * HU-31: nota libre por ítem (hasta 60 caracteres) que sale en la comanda para
 * cocina y en ningún otro ticket. La edición en el pedido la atiende pos.js.
 */
final class NotaItemTest extends CasoPrueba
{
    private int $caja;

    protected function setUp(): void
    {
        parent::setUp();
        Reloj::fijar('2026-10-21 12:00:00');
        $this->entrarComo('mariela', 'pin');
        $this->post('/caja/abrir', ['turno_id' => (int) DB::valor("SELECT id FROM turnos WHERE clave = 'mediodia'"), 'fondo' => '300']);
        $this->caja = (int) DB::valor("SELECT id FROM cajas WHERE estado = 'abierta'");
    }

    /** @param array<string, string> $notas código de producto => nota */
    private function cobrar(array $notas): Respuesta
    {
        $lineas = [];
        $total = 0.0;
        foreach ($notas as $codigo => $nota) {
            $p = DB::fila('SELECT id, precio FROM productos WHERE codigo = ?', [$codigo]);
            $lineas[] = ['id' => (int) $p['id'], 'cantidad' => 1, 'nota' => $nota];
            $total += (float) $p['precio'];
        }
        return $this->post('/pos/cobrar', [
            'uuid' => '12345678-1234-4123-a123-' . bin2hex(random_bytes(6)),
            'caja_id' => (string) $this->caja, 'tipo' => 'mesa', 'mesa' => '3', 'exacto' => '1',
            'total_esperado' => number_format($total, 2, '.', ''), 'lineas' => json_encode($lineas),
        ]);
    }

    /** Parte del HTML de los tickets: comanda, copia de caja o factura. */
    private function ticket(string $html, string $id): string
    {
        $this->assertSame(1, preg_match('/<article class="ticket [^"]*" id="' . $id . '">(.*?)<\/article>/s', $html, $m));
        return $m[1];
    }

    /**
     * Escenario: Nota en la comanda
     *   Dado agrego un Majadito con la nota «sin cebolla»
     *   Cuando cobro el pedido
     *   Entonces la comanda muestra la nota bajo el plato, y la copia de caja y la factura no
     */
    public function testLaNotaSaleSoloEnLaComanda(): void
    {
        $r = $this->cobrar(['PL-01' => '  sin cebolla  ', 'PL-02' => '']);

        $this->assertSame(200, $r->estado, $r->cuerpo);
        $id = (int) json_decode($r->cuerpo, true)['id'];
        $html = $this->get('/facturas/' . $id)->cuerpo;
        $this->assertStringContainsString('<span class="comanda__nombre">Majadito de charque</span><span class="comanda__nota">» SIN CEBOLLA</span>', $this->ticket($html, 'ticket-comanda'));
        $this->assertStringNotContainsStringIgnoringCase('cebolla', $this->ticket($html, 'ticket-copia'));
        $this->assertStringNotContainsStringIgnoringCase('cebolla', $this->ticket($html, 'ticket-factura'));
        $this->assertSame(1, substr_count($html, 'comanda__nota'));

        $notas = array_column(DB::filas('SELECT nota FROM detalle_venta ORDER BY id'), 'nota');
        $this->assertSame(['sin cebolla', null], $notas);
    }

    /**
     * Escenario: La nota se muestra como texto
     *   Dado escribo una nota con etiquetas HTML
     *   Cuando abro la comanda
     *   Entonces se ve el texto tal cual, sin interpretarse
     */
    public function testLaNotaSeEscapa(): void
    {
        $r = $this->cobrar(['PL-01' => '<b onclick="x()">más ají</b>']);

        $id = (int) json_decode($r->cuerpo, true)['id'];
        $comanda = $this->ticket($this->get('/facturas/' . $id)->cuerpo, 'ticket-comanda');
        $this->assertStringContainsString('» &lt;B ONCLICK=&quot;X()&quot;&gt;MÁS AJÍ&lt;/B&gt;', $comanda);
        $this->assertStringNotContainsString('<b', $comanda);
    }

    /**
     * Escenario: Límite de 60 caracteres
     *   Dado una nota de más de 60 caracteres
     *   Cuando intento cobrar
     *   Entonces el cobro se rechaza y no se guarda la venta; con 60 justos sí se cobra
     */
    public function testLaNotaTieneHasta60Caracteres(): void
    {
        $r = $this->cobrar(['PL-01' => str_repeat('ñ', 61)]);
        $this->assertSame(422, $r->estado);
        $this->assertSame('Revisa las líneas repetidas o las notas del pedido.', json_decode($r->cuerpo, true)['error']);
        $this->assertSame(0, (int) DB::valor('SELECT COUNT(*) FROM ventas'));

        $this->assertSame(200, $this->cobrar(['PL-01' => str_repeat('ñ', 60)])->estado);
        $this->assertSame(str_repeat('ñ', 60), DB::valor('SELECT nota FROM detalle_venta'));
    }

    /**
     * Escenario: Solo lo que va a cocina lleva nota
     *   Dado el pedido tiene un plato y una bebida
     *   Cuando abro el punto de venta
     *   Entonces el plato ofrece la nota para cocina y la bebida no, porque no sale en la comanda
     */
    public function testLaNotaSeOfreceSoloEnLoQueVaACocina(): void
    {
        $html = $this->get('/pos')->cuerpo;
        preg_match('/data-productos="([^"]+)"/', $html, $m);
        $datos = array_column(json_decode(html_entity_decode($m[1]), true), 'cocina', 'codigo');

        $this->assertTrue($datos['PL-01']);
        $this->assertFalse($datos['RE-01']);
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/pos.js');
        $this->assertStringContainsString('if (p.cocina) {', $js);
        $this->assertStringContainsString('maxlength="60"', $js);
    }
}
