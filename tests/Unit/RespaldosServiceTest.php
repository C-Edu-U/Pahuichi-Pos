<?php

declare(strict_types=1);
namespace Tests\Unit;
use App\Services\RespaldosService;
use DomainException;
use PHPUnit\Framework\TestCase;

final class RespaldosServiceTest extends TestCase
{
    private string $dir;
    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/hu07-' . bin2hex(random_bytes(8));
        mkdir($this->dir); mkdir($this->dir . '/.solicitudes');
        file_put_contents($this->dir . '/.estado.json', json_encode(['ahora'=>time(),'hora'=>'02:00','proxima'=>time()+86400]));
    }
    protected function tearDown(): void
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $p) {
            $p->isDir() ? rmdir($p->getPathname()) : unlink($p->getPathname());
        }
        rmdir($this->dir);
    }
    public function testCuentaConservacionSinLeerElContenidoSql(): void
    {
        $nombre='pahuichi-2026-10-24_020000.ABCDEF.sql'; file_put_contents($this->dir.'/'.$nombre,'-- privado');
        touch($this->dir.'/'.$nombre,time()-86400);
        $e=(new RespaldosService($this->dir))->estado();
        self::assertTrue($e['activo']);self::assertCount(1,$e['copias']);
        self::assertSame('2026-10-24 02:00:00',$e['copias'][0]['fecha']);
        self::assertEqualsWithDelta(time()+29*86400,$e['copias'][0]['vence'],2);
        self::assertStringNotContainsString('privado',json_encode($e));
    }
    public function testLaSolicitudDuraCincoSegundosYElDobleClicNoLaDuplica(): void
    {
        $s=new RespaldosService($this->dir); $r=$s->solicitar('copiar','');
        self::assertEqualsWithDelta(time()+5,$r['programada'],1);
        self::assertSame($r,$s->solicitar('copiar',''));
        self::assertCount(1,glob($this->dir.'/.solicitudes/*.req'));
        self::assertSame($r,$s->estado($r['id'])['resultado']);
    }
    public function testSolicitudTerminadaNoBloqueaNiSeRepite(): void
    {
        $s=new RespaldosService($this->dir);$r=$s->solicitar('copiar','');
        file_put_contents($this->dir.'/.solicitudes/'.$r['id'].'.json',json_encode($r+['mensaje'=>'Lista']));
        $terminada=$r; $terminada['estado']='hecho';
        file_put_contents($this->dir.'/.solicitudes/'.$r['id'].'.json',json_encode($terminada));
        self::assertNull($s->estado()['pendiente']);
        self::assertSame('hecho',$s->estado($r['id'])['resultado']['estado']);
    }
    public function testNoAceptaRutasNiAccionesArbitrarias(): void
    {
        foreach ([['vencer','../.env'],['borrar',''],['vencer','no-existe.sql']] as [$accion,$archivo]) {
            try {(new RespaldosService($this->dir))->solicitar($accion,$archivo); self::fail('Debia rechazarlo');}
            catch(DomainException $e){self::assertNotEmpty($e->getMessage());}
        }
        self::assertSame([],glob($this->dir.'/.solicitudes/*.req'));
    }
    public function testNoProgramaSiElContenedorEstaDesconectado(): void
    {
        file_put_contents($this->dir.'/.estado.json',json_encode(['ahora'=>time()-60]));
        $this->expectException(DomainException::class);
        (new RespaldosService($this->dir))->solicitar('copiar','');
    }
    public function testNoPermiteDosSimulacionesDiferentesALaVez(): void
    {
        $n='pahuichi-2026-10-24_020000.ABCDEF.sql';file_put_contents($this->dir.'/'.$n,'-- copia');
        $s=new RespaldosService($this->dir);$s->solicitar('copiar','');
        $this->expectException(DomainException::class);$s->solicitar('vencer',$n);
    }
}
