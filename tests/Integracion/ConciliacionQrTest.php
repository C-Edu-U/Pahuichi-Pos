<?php
declare(strict_types=1);
namespace Tests\Integracion;
use App\Core\{Auth,Csrf,DB,Peticion,Reloj};
use App\Models\{Usuario,Venta};
use App\Services\{ConciliacionQrService,VentaService};
use Tests\CasoPrueba;
final class ConciliacionQrTest extends CasoPrueba
{
    private int $caja;
    private int $producto;
    protected function setUp(): void
    {
        parent::setUp();Reloj::fijar('2026-10-25 12:00:00');$this->entrarComo('mariela','pin');
        $this->post('/caja/abrir',['turno_id'=>(int)DB::valor("SELECT id FROM turnos WHERE clave='mediodia'"),'fondo'=>'200']);
        $this->caja=(int)DB::valor("SELECT id FROM cajas WHERE estado='abierta'");
        DB::consulta('UPDATE promociones SET activo=0');$this->producto=(int)DB::valor("SELECT id FROM productos WHERE codigo='PL-01'");DB::consulta('UPDATE productos SET precio=90 WHERE id=?',[$this->producto]);
    }
    private function pago(string $fecha='2026-10-25'): int
    {
        $this->entrarComo('mariela','pin');Reloj::fijar($fecha.' 12:00:00');
        $qr=DB::insertar('INSERT INTO qr_diarios (fecha,monto,imagen,imagen_tipo,usuario_id,creado_en) VALUES (?,0,?,\'image/png\',1,?)',[$fecha,'imagen',Reloj::sql()]);
        $f=VentaService::cobrar(['uuid'=>'12345678-1234-4123-a123-'.bin2hex(random_bytes(6)),'caja_id'=>(string)$this->caja,'tipo'=>'llevar','mesa'=>'','total_esperado'=>'90','metodo'=>'qr','qr_id'=>(string)$qr,'qr_monto'=>'90','comprobante'=>'HU41-'.bin2hex(random_bytes(6)),'lineas'=>[['id'=>$this->producto,'cantidad'=>1,'nota'=>'']]]);
        return (int)DB::valor('SELECT id FROM pagos WHERE venta_id=?',[$f['venta_id']]);
    }
    public function testFiltrosPorFechaTurnoYEstadoSeCombinan(): void
    {
        $ayer=$this->pago('2026-10-24');$hoy=$this->pago();$this->entrarComo('admin');ConciliacionQrService::resolver($ayer,'confirmado');
        $turno=(string)DB::valor('SELECT turno_id FROM cajas WHERE id=?',[$this->caja]);
        $r=ConciliacionQrService::listar(['fecha'=>'2026-10-25','turno'=>$turno,'estado'=>'pendiente']);$this->assertSame([$hoy],array_column($r['filas'],'id'));
        $r=ConciliacionQrService::listar(['fecha'=>'2026-10-24','turno'=>$turno,'estado'=>'confirmado']);$this->assertSame([$ayer],array_column($r['filas'],'id'));
        $otro=(string)DB::valor('SELECT id FROM turnos WHERE id<>? LIMIT 1',[$turno]);
        $this->assertSame([],ConciliacionQrService::listar(['fecha'=>'2026-10-25','turno'=>$otro,'estado'=>'pendiente'])['filas']);
    }
    public function testConfirmarAuditaUnaVezYNoAumentaEfectivo(): void
    {
        $id=$this->pago();$this->entrarComo('admin');$efectivo=Venta::efectivoCobrado($this->caja);
        $r=$this->post('/conciliacion/'.$id.'/resolver',['estado'=>'confirmado']);$this->assertSame(303,$r->estado);
        $this->assertSame('confirmado',DB::valor('SELECT estado FROM pagos WHERE id=?',[$id]));
        $this->assertFalse(ConciliacionQrService::resolver($id,'confirmado')['cambiado']);
        $this->assertSame(1,(int)DB::valor("SELECT COUNT(*) FROM bitacora WHERE accion='qr.confirmar' AND entidad_id=?",[(string)$id]));
        $this->assertSame($efectivo,Venta::efectivoCobrado($this->caja));
    }
    public function testRechazarCreaUnAvisoPrivadoYPersistente(): void
    {
        $id=$this->pago();$this->entrarComo('admin');ConciliacionQrService::resolver($id,'rechazado');ConciliacionQrService::resolver($id,'rechazado');
        $this->assertSame(1,(int)DB::valor('SELECT COUNT(*) FROM qr_avisos WHERE pago_id=?',[$id]));
        $this->assertSame(1,(int)DB::valor("SELECT COUNT(*) FROM bitacora WHERE accion='qr.rechazar' AND entidad_id=?",[(string)$id]));
        $aviso=(int)DB::valor('SELECT id FROM qr_avisos WHERE pago_id=?',[$id]);ConciliacionQrService::enterado($aviso);$this->assertNull(DB::valor('SELECT visto_en FROM qr_avisos WHERE id=?',[$aviso]));
        $this->entrarComo('mariela','pin');$this->assertCount(1,ConciliacionQrService::avisos());ConciliacionQrService::enterado($aviso);$this->assertSame([],ConciliacionQrService::avisos());
    }
    public function testCajeroNoPuedeAccederResolverNiVerFotosYPostExigeCsrf(): void
    {
        $id=$this->pago();$this->assertSame(403,$this->get('/conciliacion')->estado);
        $this->assertSame(403,$this->get('/conciliacion/'.$id.'/foto')->estado);
        $this->assertSame(403,$this->post('/conciliacion/'.$id.'/resolver',['estado'=>'confirmado'])->estado);
        $this->entrarComo('admin');$r=$this->post('/conciliacion/'.$id.'/resolver',['_csrf'=>'invalido','estado'=>'confirmado']);$this->assertContains($r->estado,[403,419]);
        $this->assertSame('pendiente',DB::valor('SELECT estado FROM pagos WHERE id=?',[$id]));
    }
    public function testUnPagoResueltoNoPuedeCambiarAlEstadoOpuesto(): void
    {
        $id=$this->pago();$this->entrarComo('admin');ConciliacionQrService::resolver($id,'confirmado');
        $r=$this->post('/conciliacion/'.$id.'/resolver',['estado'=>'rechazado']);$this->assertSame(422,$r->estado);
        $this->assertSame('confirmado',DB::valor('SELECT estado FROM pagos WHERE id=?',[$id]));
    }
}
