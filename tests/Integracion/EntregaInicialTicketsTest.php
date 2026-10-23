<?php

declare(strict_types=1);
namespace Tests\Integracion;

use App\Core\DB;
use App\Core\Migrador;
use App\Core\Reloj;
use App\Core\Sesion;
use App\Models\Bitacora;
use App\Models\Factura;
use App\Services\EntregaInicialService;
use Tests\CasoPrueba;

/** El servidor distingue la entrega inicial de todas las recuperaciones posteriores. */
final class EntregaInicialTicketsTest extends CasoPrueba
{
    private int $id;
    private string $token;
    private string $url;
    private array $pedido;

    protected function setUp(): void
    {
        parent::setUp();
        Reloj::fijar('2026-10-23 12:00:00');
        $this->entrarComo('mariela', 'pin');
        $this->post('/caja/abrir', ['turno_id' => (int) DB::valor("SELECT id FROM turnos WHERE clave='mediodia'"), 'fondo' => '100']);
        $p = DB::fila("SELECT * FROM productos WHERE codigo='PR-01'");
        $this->pedido = [
            'uuid' => '12345678-1234-4123-a123-' . bin2hex(random_bytes(6)),
            'caja_id' => (string) DB::valor("SELECT id FROM cajas WHERE estado='abierta'"),
            'tipo' => 'mesa', 'mesa' => '1', 'exacto' => '1', 'total_esperado' => (string) $p['precio'],
            'lineas' => json_encode([['id' => (int) $p['id'], 'cantidad' => 1, 'nota' => 'Sin ají']]),
        ];
        $r = $this->post('/pos/cobrar', $this->pedido);
        $this->assertSame(200, $r->estado, $r->cuerpo);
        $d = json_decode($r->cuerpo, true);
        $this->id = $d['id']; $this->url = $d['tickets'];
        parse_str((string) parse_url($this->url, PHP_URL_QUERY), $q);
        $this->token = $q['entrega'];
    }

    private function original(string $tipo, array $extra = []): \App\Core\Respuesta
    {
        return $this->post('/facturas/' . $this->id . '/original', $extra + ['destino' => $tipo, 'entrega' => $this->token]);
    }

    public function testCobroAbreEntregaInicialYConservaPermisoAlReintentar(): void
    {
        $r = $this->post('/pos/cobrar', $this->pedido);
        $this->assertSame($this->url, json_decode($r->cuerpo, true)['tickets']);
        $html = $this->get('/facturas/' . $this->id . '/originales', ['entrega' => $this->token])->cuerpo;
        $this->assertStringContainsString('Entrega inicial de tickets', $html);
        $this->assertStringContainsString('Ver PDF de caja', $html);
        $this->assertStringNotContainsString('Ver reimpresión en PDF', $html);
        $this->assertStringContainsString('<p class="ticket__reimpresion" hidden>REIMPRESIÓN</p>', $html);
        $this->assertCount(0, Bitacora::ultimos('factura.reimpresion'));
    }

    public function testVerYDescargarConservanElMismoPdfDeCadaPerfil(): void
    {
        $f = Factura::buscar($this->id);
        $contador = DB::filas('SELECT * FROM correlativos');
        foreach (['caja', 'cocina'] as $destino) {
            $ver = $this->original($destino);
            $descargar = $this->original($destino, ['descargar' => '1']);
            $this->assertSame(200, $ver->estado);
            $this->assertSame(200, $descargar->estado);
            $this->assertStringStartsWith('%PDF-', $ver->cuerpo);
            $this->assertSame($ver->cuerpo, $descargar->cuerpo);
            $this->assertStringContainsString('inline;', $ver->cabeceras['Content-Disposition']);
            $this->assertStringContainsString('attachment;', $descargar->cabeceras['Content-Disposition']);
            $this->assertStringContainsString('Original-' . $destino, $descargar->cabeceras['Content-Disposition']);
        }
        $this->assertSame(['comanda', 'copia', 'factura'], EntregaInicialService::entregados($this->id));
        $this->assertSame($f, Factura::buscar($this->id));
        $this->assertSame($contador, DB::filas('SELECT * FROM correlativos'));
        $this->assertSame(1, (int) DB::valor('SELECT COUNT(*) FROM ventas'));
        $this->assertCount(0, Bitacora::ultimos('factura.reimpresion'));
    }

    public function testCambiarConfiguracionNoCambiaElArchivoYaAbierto(): void
    {
        $caja = $this->original('caja');
        DB::consulta("UPDATE configuracion_tickets SET factura=0, columnas=32, ancho=58 WHERE destino='caja'");
        $this->assertSame($caja->cuerpo, $this->original('caja', ['descargar' => '1'])->cuerpo);
        $this->assertSame(['copia', 'factura'], EntregaInicialService::tipos($this->id, 'caja'));
        $this->assertSame(422, $this->original('invalido')->estado);
    }

    public function testEntrarAlHistorialCierraLaEntregaInclusoConLaUrlAnterior(): void
    {
        $html = $this->get('/facturas/' . $this->id)->cuerpo;
        $this->assertStringContainsString('Ver reimpresión en PDF', $html);
        $this->assertStringNotContainsString('Ver PDF de caja', $html);
        $this->assertStringNotContainsString('Ver PDF de caja', $html);
        $this->assertSame(422, $this->original('caja')->estado);
        $this->assertRedirige('/facturas/' . $this->id, $this->get('/facturas/' . $this->id . '/originales', ['entrega' => $this->token]));
        $retry = json_decode($this->post('/pos/cobrar', $this->pedido)->cuerpo, true);
        $this->assertSame('/facturas/' . $this->id, $retry['tickets']);
        $this->assertSame(200, $this->post('/facturas/' . $this->id . '/reimpresion', ['tipo' => 'copia'])->estado);
        $this->assertCount(1, Bitacora::ultimos('factura.reimpresion'));
    }

    public function testAbrirListaDeVentasTambienCierraLaEntrega(): void
    {
        $this->assertSame(200, $this->get('/ventas')->estado);
        $this->assertSame(422, $this->original('caja')->estado);
    }

    public function testElServidorConvierteLasUrlPdfAntiguasEnReimpresionesAuditadas(): void
    {
        $r = $this->get('/facturas/' . $this->id . '/pdf', ['solo' => 'factura', 'original' => '1', 'entrega' => $this->token]);
        $this->assertSame(200, $r->estado);
        $this->assertStringContainsString('Reimpresion-', $r->cabeceras['Content-Disposition']);
        $b = Bitacora::ultimos('factura.reimpresion');
        $this->assertCount(1, $b);
        $this->assertSame('factura', json_decode($b[0]['detalle'], true)['tipo']);
        $this->assertSame(422, $this->original('caja')->estado);
    }

    public function testNoHayCaducidadArbitrariaDeTreintaMinutos(): void
    {
        Reloj::fijar('2026-10-23 12:31:00');
        $this->assertSame(200, $this->original('caja')->estado);
        $html = $this->get('/facturas/' . $this->id . '/originales', ['entrega' => $this->token])->cuerpo;
        $this->assertStringNotContainsString('30 minutos', $html);
        $this->assertStringContainsString('Descargar PDF de cocina', $html);
        $this->assertStringNotContainsString('original-tipo', $html);
    }

    public function testExigeCsrfPermisoDeSesionYAccesoALaVenta(): void
    {
        $this->assertSame(403, $this->original('caja', ['_csrf' => 'incorrecto'])->estado);
        $this->assertSame(422, $this->original('caja', ['entrega' => 'falso'])->estado);
        $this->entrarComo('jorge', 'pin');
        $this->assertSame(403, $this->original('caja')->estado);
        $this->entrarComo('admin');
        $this->assertSame(422, $this->original('caja')->estado);
        $this->assertSame([], EntregaInicialService::entregados($this->id));
    }

    public function testCerrarSesionNoPermiteCrearOtraEntregaInicial(): void
    {
        Sesion::enMemoria(); $this->entrarComo('mariela', 'pin');
        $this->assertSame(422, $this->original('caja')->estado);
        $retry = json_decode($this->post('/pos/cobrar', $this->pedido)->cuerpo, true);
        $this->assertSame('/facturas/' . $this->id, $retry['tickets']);
    }

    public function testLasFacturasPreviasALaMigracionSonHistoricas(): void
    {
        DB::consulta('DELETE FROM entregas_tickets WHERE factura_id=?', [$this->id]);
        $sql = file_get_contents(dirname(__DIR__, 2) . '/database/migraciones/065_hu56_entregas_tickets.sql');
        $sentencias = Migrador::sentencias($sql);
        DB::consulta($sentencias[count($sentencias) - 1]);
        $this->assertNull(EntregaInicialService::abrir(Factura::buscar($this->id)));
        $this->assertSame(422, $this->original('caja')->estado);
    }
    public function testConsultaAdministrativaDeLaMismaFacturaSinReabrirEntregaNiCambiarDatos(): void
    {
        $this->get('/facturas/' . $this->id);
        $antes = Factura::buscar($this->id);
        $correlativos = DB::filas('SELECT * FROM correlativos');
        $pagos = DB::filas('SELECT * FROM pagos');
        $this->entrarComo('admin');
        $html = $this->get('/facturas/' . $this->id)->cuerpo;
        $this->assertStringContainsString('Consultar documentos emitidos', $html);
        $this->assertStringNotContainsString('trucoticket', $html);
        $r = $this->post('/facturas/' . $this->id . '/acceso-original');
        $this->assertSame(303, $r->estado, $r->cuerpo);
        $url = $r->cabeceras['Location'];
        $this->assertStringStartsWith('/facturas/' . $this->id . '/originales?', $url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        $this->token = $q['entrega'];
        $this->assertSame(1, (int) DB::valor('SELECT cerrada FROM entregas_tickets WHERE factura_id=?', [$this->id]));
        $this->assertSame(200, $this->original('caja')->estado);
        $this->assertStringContainsString('Documentos emitidos de esta venta',
            $this->get('/facturas/' . $this->id . '/originales', $q)->cuerpo);
        $this->assertSame($antes, Factura::buscar($this->id));
        $this->assertSame($correlativos, DB::filas('SELECT * FROM correlativos'));
        $this->assertSame($pagos, DB::filas('SELECT * FROM pagos'));
        $b = Bitacora::ultimos('factura.consulta_original');
        $this->assertCount(1, $b);
        $this->assertSame($this->id, (int) $b[0]['entidad_id']);
        $this->assertCount(0, Bitacora::ultimos('factura.reimpresion'));
        $this->get('/facturas/' . $this->id);
        $this->assertSame(422, $this->original('caja')->estado);
    }

    public function testConsultaOriginalExigeAdministradorYCsrf(): void
    {
        $this->assertStringNotContainsString('Consultar documentos emitidos',
            $this->get('/facturas/' . $this->id)->cuerpo);
        $this->assertSame(403, $this->post('/facturas/' . $this->id . '/acceso-original')->estado);
        $this->entrarComo('admin');
        $this->assertSame(403, $this->post('/facturas/' . $this->id . '/acceso-original', ['_csrf'=>'falso'])->estado);
        $this->assertSame(404, $this->post('/facturas/999999999/acceso-original')->estado);
        $this->assertCount(0, Bitacora::ultimos('factura.consulta_original'));
    }

    public function testConsultaOriginalRechazaFacturaAnulada(): void
    {
        $this->entrarComo('admin');
        DB::consulta("UPDATE facturas SET estado='anulada' WHERE id=?", [$this->id]);
        $this->assertSame(422, $this->post('/facturas/' . $this->id . '/acceso-original')->estado);
        $this->assertCount(0, Bitacora::ultimos('factura.consulta_original'));
    }

    public function testConsultaOriginalNoSeTransfiereAOtraSesion(): void
    {
        $this->get('/facturas/' . $this->id);
        $this->entrarComo('admin');
        $r=$this->post('/facturas/' . $this->id . '/acceso-original');
        parse_str((string) parse_url($r->cabeceras['Location'], PHP_URL_QUERY), $q);
        Sesion::enMemoria(); $this->entrarComo('admin');
        $this->token=$q['entrega'];
        $this->assertSame(422, $this->original('caja')->estado);
    }

    public function testCajeroPuedeConsultarAlActivarYSeRevocaInclusoElPdfCacheado(): void
    {
        $this->get('/facturas/' . $this->id);
        $this->entrarComo('admin');
        $this->assertSame(303, $this->post('/config/tickets-originales', ['originales_cajeros' => '1'])->estado);
        $html = $this->get('/config')->cuerpo;
        $this->assertStringContainsString('name="originales_cajeros" value="1" checked', $html);
        $this->entrarComo('mariela', 'pin');
        $this->assertStringContainsString('Consultar documentos emitidos', $this->get('/facturas/' . $this->id)->cuerpo);
        $r = $this->post('/facturas/' . $this->id . '/acceso-original');
        $this->assertSame(303, $r->estado, $r->cuerpo);
        parse_str((string) parse_url($r->cabeceras['Location'], PHP_URL_QUERY), $q);
        $this->token = $q['entrega'];
        $ver = $this->original('caja');
        $this->assertSame(200, $ver->estado);
        $this->assertSame($ver->cuerpo, $this->original('caja', ['descargar' => '1'])->cuerpo);
        $b = Bitacora::ultimos('factura.consulta_original');
        $this->assertCount(1, $b);
        $this->assertSame('cajero', json_decode($b[0]['detalle'], true)['usuario_rol']);
        // Revocación por otra sesión: conserva la sesión del cajero y su PDF ya generado.
        \App\Models\Configuracion::guardar(\App\Models\Configuracion::ORIGINALES_CAJEROS, '0');
        $this->assertSame(422, $this->original('caja')->estado);
        $this->assertRedirige('/facturas/' . $this->id,
            $this->get('/facturas/' . $this->id . '/originales', $q));
        $this->assertSame(403, $this->post('/facturas/' . $this->id . '/acceso-original')->estado);
    }

    public function testSoloAdministradorPuedeCambiarPermisoYCajeroNoAccedeAVentaAjena(): void
    {
        $this->assertSame(403, $this->post('/config/tickets-originales', ['originales_cajeros' => '1'])->estado);
        $this->entrarComo('admin');
        $this->assertSame(403, $this->post('/config/tickets-originales', ['_csrf' => 'falso', 'originales_cajeros' => '1'])->estado);
        $this->assertSame(303, $this->post('/config/tickets-originales', ['originales_cajeros' => '1'])->estado);
        $this->entrarComo('jorge', 'pin');
        $this->assertSame(403, $this->post('/facturas/' . $this->id . '/acceso-original')->estado);
        $this->entrarComo('admin');
        $this->assertSame(303, $this->post('/config/tickets-originales')->estado);
        $this->assertSame('0', \App\Models\Configuracion::valor(\App\Models\Configuracion::ORIGINALES_CAJEROS));
        $this->assertStringContainsString('Consultar documentos emitidos', $this->get('/facturas/' . $this->id)->cuerpo);
        $this->assertCount(2, Bitacora::ultimos('config.tickets_originales'));
    }

}
