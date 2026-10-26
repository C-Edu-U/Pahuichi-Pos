<?php

declare(strict_types=1);
namespace Tests\Integracion;
use App\Core\DB;
use App\Core\Reloj;
use App\Models\Bitacora;
use App\Models\Factura;
use App\Services\TicketsPdf;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\CasoPrueba;

final class SeleccionTicketsTest extends CasoPrueba
{
    private string $url;
    private int $id;
    protected function setUp(): void
    {
        parent::setUp(); Reloj::fijar('2026-10-23 12:00:00');
        $this->entrarComo('mariela', 'pin');
        $this->post('/caja/abrir', ['turno_id' => (int) DB::valor("SELECT id FROM turnos WHERE clave='mediodia'"), 'fondo' => '100']);
        $p = DB::fila("SELECT * FROM productos WHERE codigo='PR-01'");
        $r = $this->post('/pos/cobrar', [
            'uuid' => '12345678-1234-4123-a123-' . bin2hex(random_bytes(6)),
            'caja_id' => (string) DB::valor("SELECT id FROM cajas WHERE estado='abierta'"),
            'tipo' => 'mesa', 'mesa' => '1', 'exacto' => '1', 'total_esperado' => (string) $p['precio'],
            'lineas' => json_encode([['id' => (int) $p['id'], 'cantidad' => 1, 'nota' => 'Sin ají']]),
        ]);
        $this->assertSame(200, $r->estado, $r->cuerpo);
        $this->id = json_decode($r->cuerpo, true)['id'];$this->url='/facturas/' . $this->id . '/pdf';
        DB::consulta("UPDATE configuracion_tickets SET ancho=58, columnas=32 WHERE destino='caja'");
        DB::consulta("UPDATE configuracion_tickets SET ancho=76, columnas=42 WHERE destino='cocina'");
    }
    public static function selecciones(): array
    {
        $r=[];foreach (['comanda'=>['comanda'],'copia'=>['copia'],'factura'=>['factura'],'caja'=>['copia','factura'],'todos'=>['comanda','copia','factura']] as $clave=>$tipos) {
            foreach (['0','1'] as $modo) $r[] = [$clave,$tipos,$modo];
        } return $r;
    }
    #[DataProvider('selecciones')]
    public function testCincoSeleccionesEnAmbosModosConAnchosYAuditoriaCorrectos(string $seleccion,array $tipos,string $modo): void
    {
        $antes=Factura::buscar($this->id);$correlativos=DB::filas('SELECT * FROM correlativos');
        $r=$this->get($this->url,['seleccion'=>$seleccion,'reimpresion'=>$modo]);
        $this->assertSame(200,$r->estado,$r->cuerpo);$this->assertStringStartsWith('%PDF-',$r->cuerpo);
        $this->assertSame(count($tipos),preg_match_all('~/Type /Page\b~',$r->cuerpo));
        $this->assertStringContainsString('inline;',$r->cabeceras['Content-Disposition']);
        preg_match_all('~/MediaBox\s*\[([^\]]+)\]~',$r->cuerpo,$boxes);
        $this->assertCount(count($tipos)+1,$boxes[1]);
        foreach ($tipos as $i=>$tipo) {
            $valores=preg_split('/\s+/',trim($boxes[1][$i+1]));
            $this->assertEqualsWithDelta(($tipo==='comanda'?76:58)*72/25.4,(float)$valores[2],0.002);
        }
        $this->assertSame($tipos,TicketsPdf::prepararSeleccion($seleccion)['tipos']);
        $b=Bitacora::ultimos('factura.reimpresion');$this->assertCount($modo==='1'?count($tipos):0,$b);
        foreach ($b as $fila) {
            $d=json_decode($fila['detalle'],true);$this->assertContains($d['tipo'],$tipos);
            $this->assertSame('ver',$d['operacion']);
            $this->assertSame($d['tipo']==='comanda'?'cocina':'caja',$d['destino']);
        }
        $this->assertSame($antes,Factura::buscar($this->id));
        $this->assertSame($correlativos,DB::filas('SELECT * FROM correlativos'));
        $this->assertSame(1,(int)DB::valor('SELECT COUNT(*) FROM ventas'));
    }
    public function testDescargarLosTresRespetaCopiasDeCadaPerfil(): void
    {
        DB::consulta("UPDATE configuracion_tickets SET copias=2 WHERE destino='caja'");
        DB::consulta("UPDATE configuracion_tickets SET copias=3 WHERE destino='cocina'");
        $r=$this->get($this->url,['seleccion'=>'todos','descargar'=>'1','reimpresion'=>'1']);
        $this->assertSame(200,$r->estado,$r->cuerpo);
        $this->assertSame(7,preg_match_all('~/Type /Page\b~',$r->cuerpo));
        $this->assertStringContainsString('attachment;',$r->cabeceras['Content-Disposition']);
        $this->assertCount(3,Bitacora::ultimos('factura.reimpresion'));
        foreach(Bitacora::ultimos('factura.reimpresion') as $b){$d=json_decode($b['detalle'],true);$this->assertSame($d['tipo']==='comanda'?3:2,$d['copias']);$this->assertSame('descargar',$d['operacion']);}
    }
    public function testRechazaSeleccionManipuladaSinGenerarRegistros(): void
    {
        foreach(['','../factura','otro',['todos']] as $s)$this->assertSame(422,$this->get($this->url,['seleccion'=>$s])->estado);
        $this->assertSame(422,$this->get($this->url,['seleccion'=>'todos','descargar'=>'otro'])->estado);
        $this->assertCount(0,Bitacora::ultimos('factura.reimpresion'));
        $this->entrarComo('jorge','pin');$this->assertSame(403,$this->get($this->url,['seleccion'=>'todos'])->estado);
    }
    public function testCadaPaginaSeAjustaAlContenidoSinHeredarLaAlturaDeLaFactura(): void
    {
        $medidas = [];
        foreach (['comanda','copia','factura','caja','todos'] as $seleccion) {
            $r=$this->get($this->url,['seleccion'=>$seleccion]);
            $this->assertSame(200,$r->estado,$r->cuerpo);
            preg_match_all('~/MediaBox\s*\[([^\]]+)\]~',$r->cuerpo,$boxes);
            $paginas=[];
            foreach(array_slice($boxes[1],1) as $box){$v=array_map('floatval',preg_split('/\s+/',trim($box)));$paginas[]=$v[3]-$v[1];}
            $medidas[$seleccion]=$paginas;
        }
        $this->assertSame([$medidas['comanda'][0],$medidas['copia'][0],$medidas['factura'][0]],$medidas['todos']);
        $this->assertSame([$medidas['copia'][0],$medidas['factura'][0]],$medidas['caja']);
        $this->assertLessThan($medidas['factura'][0],$medidas['comanda'][0]);
        $this->assertLessThan($medidas['factura'][0],$medidas['copia'][0]);
        $this->assertGreaterThan(36,$medidas['comanda'][0]);
    }

}
