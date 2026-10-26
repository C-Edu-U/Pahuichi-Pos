<?php

declare(strict_types=1);
namespace Tests\Integracion;

use App\Core\DB;
use App\Core\Reloj;
use App\Models\Factura;
use Tests\CasoPrueba;

/** HU-55: verifica la comanda ya existente, sin duplicar su implementación. */
final class ComandaCocinaTest extends CasoPrueba
{
    private array $plato;
    private array $soda;

    protected function setUp(): void
    {
        parent::setUp();
        Reloj::fijar('2026-10-22 12:34:00');
        $this->entrarComo('mariela', 'pin');
        $this->post('/caja/abrir', ['turno_id' => (int) DB::valor("SELECT id FROM turnos WHERE clave='mediodia'"), 'fondo' => '100']);
        $this->plato = DB::fila("SELECT * FROM productos WHERE codigo='PL-01'");
        $this->soda = DB::fila("SELECT * FROM productos WHERE codigo='SO-01'");
        $this->assertSame(1, (int) $this->plato['va_a_cocina']);
        $this->assertSame(0, (int) $this->soda['va_a_cocina']);
    }

    private function pedido(string $tipo = 'mesa', bool $soloSoda = false, string $nota = 'sin ají'): array
    {
        $lineas = [['id' => (int) $this->soda['id'], 'cantidad' => 1, 'nota' => 'Soda muy fría']];
        if (!$soloSoda) array_unshift($lineas, ['id' => (int) $this->plato['id'], 'cantidad' => 2, 'nota' => $nota]);
        return ['uuid' => '12345678-1234-4123-a123-' . bin2hex(random_bytes(6)),
            'caja_id' => (string) DB::valor("SELECT id FROM cajas WHERE estado='abierta'"), 'tipo' => $tipo,
            'mesa' => $tipo === 'mesa' ? '5' : '', 'exacto' => '1', 'total_esperado' => $soloSoda ? '8.00' : '88.00',
            'lineas' => json_encode($lineas, JSON_UNESCAPED_UNICODE)];
    }

    private function vender(string $tipo = 'mesa', bool $soloSoda = false): array
    {
        $r = $this->post('/pos/cobrar', $this->pedido($tipo, $soloSoda));
        $this->assertSame(200, $r->estado, $r->cuerpo);
        return Factura::buscar((int) json_decode($r->cuerpo, true)['id']);
    }

    private function comanda(int $id): \DOMElement
    {
        $r = $this->get('/facturas/' . $id);
        $this->assertSame(200, $r->estado);
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $r->cuerpo);
        $nodo = (new \DOMXPath($dom))->query('//article[@id="ticket-comanda"]')->item(0);
        $this->assertInstanceOf(\DOMElement::class, $nodo);
        return $nodo;
    }

    public function testMesaCincoPedidoHoraCantidadNotaSinPrecios(): void
    {
        $f = $this->vender();
        $nodo = $this->comanda($f['id']);
        $texto = $nodo->textContent;
        $this->assertStringContainsString($f['snapshot']['pedido'], $texto);
        $this->assertStringContainsString('MESA 5', $texto);
        $this->assertStringContainsString('22/10/2026 12:34', $texto);
        $this->assertStringContainsString('2 ×', $texto);
        $this->assertStringContainsString('Majadito de charque', $texto);
        $this->assertStringContainsString('SIN AJÍ', $texto);
        foreach (['40,00', '80,00', '8,00', '88,00', 'TOTAL', 'Bs'] as $precio) $this->assertStringNotContainsString($precio, $texto);
    }

    public function testSoloAparecenItemsMarcadosVaACocina(): void
    {
        $f = $this->vender();
        $this->assertCount(2, $f['snapshot']['lineas']);
        $texto = $this->comanda($f['id'])->textContent;
        $this->assertStringContainsString('Majadito de charque', $texto);
        $this->assertStringNotContainsString('Soda cola 500 ml', $texto);
        $this->assertStringNotContainsString('SODA MUY FRÍA', $texto);
    }

    public function testParaLlevarNoMuestraUnaMesa(): void
    {
        $texto = $this->comanda($this->vender('llevar')['id'])->textContent;
        $this->assertStringContainsString('PARA LLEVAR', $texto);
        $this->assertStringNotContainsString('MESA', $texto);
    }

    public function testPdfDeCocinaUsaComandaSinEnviarAImprimir(): void
    {
        $f = $this->vender();
        $r = $this->get('/facturas/' . $f['id'] . '/pdf', ['destino' => 'cocina', 'descargar' => '1']);
        $this->assertSame(200, $r->estado);
        $this->assertSame('application/pdf', $r->cabeceras['Content-Type']);
        $this->assertStringContainsString('attachment;', $r->cabeceras['Content-Disposition']);
        $this->assertStringStartsWith('%PDF-', $r->cuerpo);
        $this->assertSame(1, preg_match_all('~/Type /Page\b~', $r->cuerpo));
        $this->assertStringNotContainsString('/JavaScript', $r->cuerpo);
        $this->assertStringNotContainsString('/OpenAction', $r->cuerpo);
    }

    public function testComandaConservaSnapshotAunqueCambieElProducto(): void
    {
        $f = $this->vender();
        DB::consulta("UPDATE productos SET nombre='Nombre nuevo', va_a_cocina=0 WHERE id=?", [$this->plato['id']]);
        $texto = $this->comanda($f['id'])->textContent;
        $this->assertStringContainsString('Majadito de charque', $texto);
        $this->assertStringNotContainsString('Nombre nuevo', $texto);
        $this->assertStringContainsString('SIN AJÍ', $texto);
    }

    public function testPedidoSinItemsDeCocinaNoFiltraPreciosNiBebidas(): void
    {
        $texto = $this->comanda($this->vender('llevar', true)['id'])->textContent;
        $this->assertStringContainsString('Sin platos para cocina.', $texto);
        $this->assertStringNotContainsString('Soda cola', $texto);
        $this->assertStringNotContainsString('8,00', $texto);
    }
}
