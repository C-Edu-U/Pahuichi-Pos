<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Config;
use App\Services\FotoProducto;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FotoProductoTest extends TestCase
{
    public function testRecortaUnaImagenDeTresMegasYEliminaMetadatos(): void
    {
        $im = imagecreatetruecolor(1200, 900);
        // Ruido reproducible: el PNG original supera 3 MB, con relación no cuadrada.
        for ($y = 0; $y < 900; $y++) for ($x = 0; $x < 1200; $x++) {
            imagesetpixel($im, $x, $y, (($x * 7919 + $y * 104729) ^ ($x * $y * 31)) & 0xffffff);
        }
        ob_start(); imagepng($im, null, 0); $original = ob_get_clean(); imagedestroy($im);
        $this->assertGreaterThanOrEqual(3 * 1024 * 1024, strlen($original));
        $jpeg = FotoProducto::comprimir($original);
        $this->assertLessThanOrEqual(200 * 1024, strlen($jpeg));
        $tam = getimagesizefromstring($jpeg);
        $this->assertSame([480, 480], [$tam[0], $tam[1]]);
        $this->assertSame('image/jpeg', $tam['mime']);
    }

    public function testElRecorteUsaElCentroYNoDeformaLaFoto(): void
    {
        $im = imagecreatetruecolor(900, 300);
        imagefill($im, 0, 0, 0xff0000);
        imagefilledrectangle($im, 300, 0, 599, 299, 0x00ff00);
        ob_start(); imagepng($im); $png = ob_get_clean(); imagedestroy($im);
        $foto = imagecreatefromstring(FotoProducto::comprimir($png));
        $rgb = imagecolorat($foto, 5, 240);
        $this->assertGreaterThan(240, ($rgb >> 8) & 255);
        $this->assertLessThan(10, ($rgb >> 16) & 255);
        imagedestroy($foto);
    }

    public function testPngTransparenteQuedaSobreFondoBlanco(): void
    {
        $im = imagecreatetruecolor(50, 60); imagealphablending($im, false); imagesavealpha($im, true);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
        ob_start(); imagepng($im); $png = ob_get_clean(); imagedestroy($im);
        $foto = imagecreatefromstring(FotoProducto::comprimir($png));
        $this->assertGreaterThan(250, imagecolorat($foto, 100, 100) & 255); imagedestroy($foto);
    }

    public function testAceptaWebpYProduceJpeg(): void
    {
        $im = imagecreatetruecolor(100, 80); ob_start(); imagewebp($im); $webp = ob_get_clean(); imagedestroy($im);
        $this->assertSame('image/jpeg', getimagesizefromstring(FotoProducto::comprimir($webp))['mime']);
    }

    public function testCreaLaCarpetaDeFotosSiFalta(): void
    {
        // En un hosting, el FTP puede no subir storage/subidas si va vacía
        $antes = Config::get('app.subidas');
        $carpeta = sys_get_temp_dir() . '/pahuichi-' . bin2hex(random_bytes(4)) . '/subidas';
        Config::fijar('app.subidas', $carpeta);
        try {
            $im = imagecreatetruecolor(10, 10); ob_start(); imagejpeg($im); $jpeg = ob_get_clean(); imagedestroy($im);
            $nombre = FotoProducto::guardar($jpeg);
            $this->assertFileExists($carpeta . '/' . $nombre);
            unlink($carpeta . '/' . $nombre); rmdir($carpeta); rmdir(dirname($carpeta));
        } finally {
            Config::fijar('app.subidas', $antes);
        }
    }

    public static function invalidos(): array
    {
        return [['%PDF-1.7 documento'], ['<?php echo "ejecutable";'], ['<svg xmlns="http://www.w3.org/2000/svg"></svg>'], ["\x89PNG\r\n\x1a\nroto"], ['']];
    }

    #[DataProvider('invalidos')]
    public function testRechazaContenidoInvalidoAunqueSeLlameFotoJpg(string $bytes): void
    {
        $this->expectException(DomainException::class); FotoProducto::comprimir($bytes);
    }

    public function testRechazaEntradaMayorADiezMegas(): void
    {
        $this->expectException(DomainException::class); FotoProducto::comprimir(str_repeat('x', 10 * 1024 * 1024 + 1));
    }

    public function testRechazaImagenMayorAVeinticuatroMegapixelesAntesDeDecodificar(): void
    {
        // Cabecera PNG válida de 25 MP sin reservar el mapa de píxeles en GD.
        $chunk = function ($tipo, $datos) { return pack('N', strlen($datos)) . $tipo . $datos . pack('N', crc32($tipo . $datos)); };
        $png = "\x89PNG\r\n\x1a\n" . $chunk('IHDR', pack('NNCCCCC', 5000, 5000, 8, 2, 0, 0, 0)) . $chunk('IEND', '');
        $this->expectExceptionMessage('24 megapíxeles'); FotoProducto::comprimir($png);
    }
}
