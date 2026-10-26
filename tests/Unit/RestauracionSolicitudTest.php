<?php

declare(strict_types=1);
namespace Tests\Unit;
use App\Services\RespaldosService;
use DomainException;
use PHPUnit\Framework\TestCase;
final class RestauracionSolicitudTest extends TestCase
{
    public function testLaRestauracionSeEncolaYNoModificaLaCopiaOriginal(): void
    {
        $dir=sys_get_temp_dir().'/hu07r-'.bin2hex(random_bytes(8));mkdir($dir);mkdir($dir.'/.solicitudes');
        $nombre='pahuichi-2026-10-24_020000.ABCDEF.sql';file_put_contents($dir.'/'.$nombre,'-- copia original');
        file_put_contents($dir.'/.estado.json',json_encode(['ahora'=>time(),'hora'=>'02:00','proxima'=>time()+86400]));
        try {
            $s=new RespaldosService($dir);$r=$s->solicitar('restaurar',$nombre);
            self::assertSame('restaurar',$r['accion']);
            self::assertEqualsWithDelta(time()+1,$r['programada'],1);
            self::assertSame($r,$s->solicitar('restaurar',$nombre));
            self::assertSame('-- copia original',file_get_contents($dir.'/'.$nombre));
            self::assertSame($nombre,$s->estado()['pendiente']['archivo']);
            putenv('APP_RESTAURADA=1');
            try {$s->solicitar('restaurar',$nombre);self::fail('No debe actuar desde la replica');}
            catch(DomainException $e){self::assertStringContainsString('sistema habitual',$e->getMessage());}
        } finally {
            putenv('APP_RESTAURADA');
            foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::CHILD_FIRST) as $p){$p->isDir()?rmdir($p->getPathname()):unlink($p->getPathname());}rmdir($dir);
        }
    }
}
