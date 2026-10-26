<?php

declare(strict_types=1);
namespace Tests\Unit;
use App\Services\RespaldosService;
use PHPUnit\Framework\TestCase;
final class RespaldosSeparadosTest extends TestCase
{
    public function testSqlYArchivosInternosSeGuardanEnCarpetasDiferentes(): void
    {
        $raiz=sys_get_temp_dir().'/hu07-separado-'.bin2hex(random_bytes(8));mkdir($raiz);
        $copias=$raiz.'/copias';$interno=$raiz.'/interno';mkdir($copias);mkdir($interno);mkdir($interno.'/.solicitudes');
        $nombre='pahuichi-2026-10-24_020000.ABCDEF.sql';file_put_contents($copias.'/'.$nombre,'-- copia intacta');
        file_put_contents($interno.'/.estado.json',json_encode(['ahora'=>time(),'hora'=>'02:00','proxima'=>time()+3600]));
        file_put_contents($interno.'/.restauracion.json',json_encode(['base'=>'pahuichi_hu07_demo_'.str_repeat('a',32),'lista'=>1,'datos'=>['ventas'=>34]]));
        try {
            $s=new RespaldosService($copias,$interno);$estado=$s->estado();
            self::assertTrue($estado['activo']);self::assertCount(1,$estado['copias']);
            self::assertSame(34,$estado['restauracion']['datos']['ventas']);
            $job=$s->solicitar('copiar','');
            self::assertFileExists($interno.'/.solicitudes/'.$job['id'].'.req');
            self::assertSame(['.','..',$nombre],scandir($copias));
            self::assertSame('-- copia intacta',file_get_contents($copias.'/'.$nombre));
        } finally {
            foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($raiz,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::CHILD_FIRST) as $p){$p->isDir()?rmdir($p->getPathname()):unlink($p->getPathname());}rmdir($raiz);
        }
    }
}
