<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use DomainException;
use finfo;

/** HU-15. Siempre decodifica y recodifica: ni el nombre ni el MIME del cliente son confiables. */
final class FotoProducto
{
    public const MAX_ENTRADA = 10 * 1024 * 1024;
    public const MAX_SALIDA = 200 * 1024;
    public const MAX_PIXELES = 24000000;

    public static function recibir(array $archivo): string
    {
        if (in_array($archivo['error'] ?? -1, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            throw new DomainException('La foto supera el límite de 10 MB.');
        }
        if (($archivo['error'] ?? -1) !== UPLOAD_ERR_OK || !is_string($archivo['tmp_name'] ?? null)
            || !is_uploaded_file($archivo['tmp_name'])) {
            throw new DomainException('No se pudo recibir la foto. Selecciónala de nuevo.');
        }
        $peso = filesize($archivo['tmp_name']);
        if ($peso === false || $peso > self::MAX_ENTRADA) {
            throw new DomainException('La foto supera el límite de 10 MB.');
        }
        return self::comprimir((string) file_get_contents($archivo['tmp_name']));
    }

    /** Recorte central cuadrado de 480 px, JPEG sin metadatos y con límite estricto. */
    public static function comprimir(string $bytes): string
    {
        if (strlen($bytes) > self::MAX_ENTRADA) throw new DomainException('La foto supera el límite de 10 MB.');
        $tipo = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $tam = @getimagesizefromstring($bytes);
        if (!in_array($tipo, ['image/jpeg', 'image/png', 'image/webp'], true) || $tam === false) {
            throw new DomainException('El archivo no es una imagen JPG, PNG o WebP válida.');
        }
        if ($tam[0] < 1 || $tam[1] < 1 || $tam[0] * $tam[1] > self::MAX_PIXELES) {
            throw new DomainException('La foto debe tener como máximo 24 megapíxeles.');
        }
        $origen = @imagecreatefromstring($bytes);
        if ($origen === false) throw new DomainException('No se pudo leer la imagen. Elige otra foto.');
        try {
            $lado = min(imagesx($origen), imagesy($origen));
            $destino = imagecreatetruecolor(480, 480);
            try {
                imagefill($destino, 0, 0, imagecolorallocate($destino, 255, 255, 255));
                imagecopyresampled($destino, $origen, 0, 0, (int) ((imagesx($origen) - $lado) / 2),
                    (int) ((imagesy($origen) - $lado) / 2), 480, 480, $lado, $lado);
                for ($calidad = 85; $calidad >= 25; $calidad -= 10) {
                    ob_start();
                    try { imagejpeg($destino, null, $calidad); $jpeg = (string) ob_get_contents(); }
                    finally { ob_end_clean(); }
                    if ($jpeg !== '' && strlen($jpeg) <= self::MAX_SALIDA) return $jpeg;
                }
            } finally { imagedestroy($destino); }
        } finally { imagedestroy($origen); }
        throw new DomainException('No se pudo comprimir la foto a 200 KB. Elige otra imagen.');
    }

    public static function guardar(string $jpeg): string
    {
        $nombre = 'foto-' . bin2hex(random_bytes(8)) . '.jpg';
        $ruta = Config::get('app.subidas') . '/' . $nombre;
        if (strlen($jpeg) > self::MAX_SALIDA || file_put_contents($ruta, $jpeg, LOCK_EX) !== strlen($jpeg)) {
            if (is_file($ruta)) unlink($ruta);
            throw new DomainException('No se pudo guardar la foto. Avisa al administrador.');
        }
        return $nombre;
    }
}
