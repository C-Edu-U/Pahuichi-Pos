<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use DomainException;
use finfo;

/**
 * Imágenes subidas (logo; en el Sprint 2, fotos de productos). El tipo se
 * comprueba por contenido, no por la extensión, y se guardan con nombre
 * aleatorio fuera de public/ (storage/subidas), así nunca se ejecutan.
 */
final class Imagenes
{
    private const TIPOS = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

    /** @param array $archivo elemento de $_FILES */
    public static function guardar(array $archivo, string $prefijo, int $maxBytes): string
    {
        if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_INI_SIZE || ($archivo['size'] ?? 0) > $maxBytes) {
            throw new DomainException('La imagen pesa más de ' . round($maxBytes / 1048576, 1) . ' MB. Elige una más liviana.');
        }
        if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])) {
            throw new DomainException('No se pudo recibir la imagen. Inténtalo de nuevo.');
        }

        $tipo = (new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']);
        if (!isset(self::TIPOS[$tipo]) || @getimagesize($archivo['tmp_name']) === false) {
            throw new DomainException('El archivo no es una imagen PNG, JPG o WebP.');
        }

        $nombre = $prefijo . '-' . bin2hex(random_bytes(8)) . '.' . self::TIPOS[$tipo];
        if (!@move_uploaded_file($archivo['tmp_name'], self::carpeta() . '/' . $nombre)) {
            throw new DomainException('No se pudo guardar la imagen. Avisa al administrador del sistema.');
        }
        return $nombre;
    }

    public static function borrar(?string $nombre): void
    {
        if ($nombre !== null && self::nombreValido($nombre) && is_file(self::carpeta() . '/' . $nombre)) {
            unlink(self::carpeta() . '/' . $nombre);
        }
    }

    /** Ruta del archivo si el nombre es uno de los nuestros (evita ../ y similares). */
    public static function ruta(string $nombre): ?string
    {
        $ruta = self::carpeta() . '/' . $nombre;
        return self::nombreValido($nombre) && is_file($ruta) ? $ruta : null;
    }

    public static function tipo(string $nombre): string
    {
        return array_search(pathinfo($nombre, PATHINFO_EXTENSION), self::TIPOS, true) ?: 'application/octet-stream';
    }

    private static function nombreValido(string $nombre): bool
    {
        return (bool) preg_match('/^[a-z]+-[0-9a-f]{16}\.(png|jpg|webp)$/', $nombre);
    }

    /** storage/subidas; se crea si falta (en un hosting, el FTP puede no subir carpetas vacías). */
    public static function carpeta(): string
    {
        $carpeta = Config::get('app.subidas');
        if (!is_dir($carpeta) && !@mkdir($carpeta, 0775, true) && !is_dir($carpeta)) {
            throw new DomainException('No se pudo crear la carpeta de imágenes. Avisa al administrador del sistema.');
        }
        return $carpeta;
    }
}
