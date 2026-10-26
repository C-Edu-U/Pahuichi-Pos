<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Peticion;
use App\Core\Reloj;
use App\Core\Respuesta;
use App\Services\Imagenes;
use DomainException;
use Throwable;

/**
 * Estado del sistema para comprobar la instalación: base conectada, migrada y con datos,
 * la carpeta de imágenes con permiso de escritura y las extensiones para las fotos.
 */
final class SaludController extends Controller
{
    public function index(Peticion $peticion): Respuesta
    {
        try {
            $datos = [
                'estado' => 'ok',
                'hora' => Reloj::ahora()->format('Y-m-d H:i:s'),
                'hora_base' => DB::valor('SELECT DATE_FORMAT(NOW(), "%Y-%m-%d %H:%i:%s")'),
                'migraciones' => (int) DB::valor('SELECT COUNT(*) FROM migraciones'),
                'usuarios' => (int) DB::valor('SELECT COUNT(*) FROM usuarios'),
                'productos' => (int) DB::valor('SELECT COUNT(*) FROM productos'),
                'subidas' => self::subidas(),
                'gd' => extension_loaded('gd'),
                'fileinfo' => extension_loaded('fileinfo'),
            ];
            return Respuesta::json($datos);
        } catch (Throwable) {
            return Respuesta::json(['estado' => 'sin base de datos'], 503);
        }
    }

    private static function subidas(): string
    {
        try {
            return is_writable(Imagenes::carpeta()) ? 'ok' : 'sin permiso de escritura';
        } catch (DomainException) {
            return 'no existe y no se pudo crear';
        }
    }
}
