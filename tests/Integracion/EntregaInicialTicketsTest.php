<?php

declare(strict_types=1);
namespace Tests\Integracion;

use App\Core\DB;
use App\Core\Reloj;
use App\Models\Bitacora;
use App\Models\Factura;
use Tests\CasoPrueba;

/** HU-56 revisada: misma pantalla compacta, modo explícito y perfiles del administrador. */
final class EntregaInicialTicketsTest extends CasoPrueba
{
    private int $id;
    private int $cajero;
    private string $url;

    protected function setUp(): void
    {
        parent::setUp();
        Reloj::fijar('2026-10-23 12:00:00');
        $this->cajero = (int) $this->entrarComo('mariela', 'pin')['id'];
        $this->post('/caja/abrir', ['turno_id' => (int) DB::valor("SELECT id FROM turnos WHERE clave='mediodia'"), 'fondo' => '100']);
        $p = DB::fila("SELECT * FROM productos WHERE codigo='PR-01'");
        $r = $this->post('/pos/cobrar', [
            'uuid' => '12345678-1234-4123-a123-' . bin2hex(random_bytes(6)),
            'caja_id' => (string) DB::valor("SELECT id FROM cajas WHERE estado='abierta'"),
            'tipo' => 'mesa', 'mesa' => '1', 'exacto' => '1', 'total_esperado' => (string) $p['precio'],
            'lineas' => json_encode([['id' => (int) $p['id'], 'cantidad' => 1, 'nota' => 'Sin ají']]),
        ]);
        $this->assertSame(200, $r->estado, $r->cuerpo);
        $d = json_decode($r->cuerpo, true); $this->id = $d['id']; $this->url = '/facturas/' . $this->id;
        $this->assertSame($this->url, $d['tickets']);
    }

    public function testVerTicketsAbreLosTresDocumentosConBotonesArribaSinFormularioLargo(): void
    {
        $html = $this->get($this->url)->cuerpo;
        $this->assertSame(3, substr_count($html, '<article class="ticket '));
        $this->assertSame(3, substr_count($html, '<p class="ticket__reimpresion" hidden>REIMPRESIÓN</p>'));
        foreach (['PDF de tickets', 'Documentos que deseas incluir', 'Qué deseas hacer', '>Reimpresión</a>'] as $boton) {
            $this->assertStringContainsString($boton, $html);
            $this->assertLessThan(strpos($html, 'id="tickets"'), strpos($html, $boton));
        }
        $this->assertStringNotContainsString('Entrega inicial de tickets', $html);
        $this->assertStringNotContainsString('class="entrega-perfil"', $html);
        $this->assertStringNotContainsString('data-ancho=', $html);
        $this->assertCount(0, Bitacora::ultimos('factura.reimpresion'));
        $this->assertStringContainsString('>Ver tickets</a>', $this->get('/ventas')->cuerpo);
    }

    public function testReimpresionCambiaSoloLaMarcaYAuditaTresTicketsSinModificarVenta(): void
    {
        $antes = Factura::buscar($this->id); $correlativos = DB::filas('SELECT * FROM correlativos');
        $normal = $this->get($this->url)->cuerpo;
        $r = $this->get($this->url, ['reimpresion' => '1']);
        $this->assertSame(200, $r->estado, $r->cuerpo);
        $this->assertSame(3, substr_count($r->cuerpo, '<p class="ticket__reimpresion">REIMPRESIÓN</p>'));
        foreach (['caja', 'cocina'] as $destino) {
            $this->assertStringContainsString('name="reimpresion" value="1"', $r->cuerpo);
            $this->assertStringContainsString('name="reimpresion" value="0"', $normal);
        }
        $n = new \DOMDocument(); @$n->loadHTML($normal); $re = new \DOMDocument(); @$re->loadHTML($r->cuerpo);
        foreach (['comanda', 'copia', 'factura'] as $tipo) {
            $this->assertSame($n->saveHTML($n->getElementById('ticket-' . $tipo)),
                str_replace('class="ticket__reimpresion"', 'class="ticket__reimpresion" hidden', $re->saveHTML($re->getElementById('ticket-' . $tipo))));
        }
        $b = Bitacora::ultimos('factura.reimpresion'); $this->assertCount(3, $b);
        foreach ($b as $fila) {
            $this->assertSame($this->cajero, (int) $fila['usuario_id']);
            $this->assertSame($this->id, (int) $fila['entidad_id']);
            $this->assertSame('vista', json_decode($fila['detalle'], true)['medio']);
        }
        $this->assertSame($antes, Factura::buscar($this->id));
        $this->assertSame($correlativos, DB::filas('SELECT * FROM correlativos'));
        $this->assertSame(1, (int) DB::valor('SELECT COUNT(*) FROM ventas'));
        $this->assertSame(1, (int) DB::valor('SELECT COUNT(*) FROM pagos'));
    }

    public function testCadaVistaUsaSuAnchoConfiguradoSinAceptarCambiosDelCajero(): void
    {
        DB::consulta("UPDATE configuracion_tickets SET ancho=58, columnas=32 WHERE destino='caja'");
        DB::consulta("UPDATE configuracion_tickets SET ancho=76, columnas=42 WHERE destino='cocina'");
        foreach (['0', '1'] as $modo) {
            $html = $this->get($this->url, ['reimpresion' => $modo, 'ancho' => '76'])->cuerpo;
            $this->assertStringContainsString('ticket-col--58" data-ticket="copia"', $html);
            $this->assertStringContainsString('ticket-col--58" data-ticket="factura"', $html);
            $this->assertStringContainsString('ticket-col--76" data-ticket="comanda"', $html);
            $this->assertStringNotContainsString('data-ancho=', $html);
            $caja = $this->get($this->url . '/pdf', ['destino' => 'caja', 'reimpresion' => $modo]);
            $this->assertSame(200, $caja->estado);
            $this->assertMatchesRegularExpression('~/MediaBox\s*\[0\.000 0\.000 164\.409~', $caja->cuerpo);
            $cocina = $this->get($this->url . '/pdf', ['destino' => 'cocina', 'reimpresion' => $modo]);
            $this->assertSame(200, $cocina->estado);
            $this->assertMatchesRegularExpression('~/MediaBox\s*\[0\.000 0\.000 215\.433~', $cocina->cuerpo);
        }
    }

    public function testCajeroYAdministradorConsultanSinActivarPermisoYSinCaducidadDeEntrega(): void
    {
        \App\Models\Configuracion::guardar('tickets_originales_cajeros', '0');
        $this->get('/ventas'); Reloj::fijar('2026-10-23 12:40:00');
        $this->assertSame(200, $this->get($this->url . '/pdf')->estado);
        $this->entrarComo('admin');
        $this->assertSame(200, $this->get($this->url . '/pdf')->estado);
        $html = $this->get('/config')->cuerpo;
        $this->assertStringNotContainsString('originales_cajeros', $html);
        $this->assertSame(404, $this->post('/config/tickets-originales')->estado);
        $this->assertStringContainsString('id="i-caja-ancho"', $html);
    }

    public function testPdfNormalYReimpresoSeDistinguenYRespetanDescargaYSoloFactura(): void
    {
        $normal = $this->get($this->url . '/pdf', ['solo' => 'factura']);
        $this->assertSame(200, $normal->estado);
        $this->assertStringContainsString('Tickets-caja-', $normal->cabeceras['Content-Disposition']);
        $this->assertCount(0, Bitacora::ultimos('factura.reimpresion'));
        $re = $this->get($this->url . '/pdf', ['solo' => 'factura', 'reimpresion' => '1', 'descargar' => '1']);
        $this->assertSame(200, $re->estado);
        $this->assertStringContainsString('attachment;', $re->cabeceras['Content-Disposition']);
        $this->assertStringContainsString('Reimpresion-caja-', $re->cabeceras['Content-Disposition']);
        $b = Bitacora::ultimos('factura.reimpresion'); $this->assertCount(1, $b);
        $this->assertSame('factura', json_decode($b[0]['detalle'], true)['tipo']);
        $this->assertSame(422, $this->get($this->url . '/pdf', ['destino' => 'cocina', 'solo' => 'factura'])->estado);
        $this->assertSame(422, $this->get($this->url, ['reimpresion' => 'invalido'])->estado);
    }

    public function testSeConservanPermisosDeVentaYCajeroNoPuedeEditarPerfiles(): void
    {
        $this->assertSame(403, $this->post('/config/tickets/caja', ['ancho' => '58'])->estado);
        $this->entrarComo('jorge', 'pin');
        $this->assertSame(403, $this->get($this->url)->estado);
        $this->assertSame(403, $this->get($this->url, ['reimpresion' => '1'])->estado);
        $this->assertSame(403, $this->get($this->url . '/pdf')->estado);
        $this->entrarComo('luis');
        $this->assertSame(403, $this->get($this->url)->estado);
    }

    public function testEnlacesAnterioresLlevanALaVistaCompacta(): void
    {
        $this->assertRedirige($this->url, $this->get($this->url . '/originales', ['entrega' => 'viejo']));
        $this->assertRedirige($this->url, $this->post($this->url . '/acceso-original'));
        $this->assertSame(200, $this->post($this->url . '/original', ['destino' => 'caja'])->estado);
        $this->assertSame(403, $this->post($this->url . '/original', ['destino' => 'caja', '_csrf' => 'falso'])->estado);
    }

    public function testVentaAnuladaNoPermiteRegenerarNiReimprimir(): void
    {
        DB::consulta("UPDATE facturas SET estado='anulada' WHERE id=?", [$this->id]);
        $this->assertSame(422, $this->get($this->url . '/pdf')->estado);
        $this->assertSame(422, $this->get($this->url, ['reimpresion' => '1'])->estado);
        $this->assertCount(0, Bitacora::ultimos('factura.reimpresion'));
    }
}
