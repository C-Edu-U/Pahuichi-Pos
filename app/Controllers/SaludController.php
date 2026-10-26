<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Peticion;
use App\Core\Reloj;
use App\Core\Respuesta;
use Throwable;

/** Estado del sistema para comprobar la instalación: base conectada, migrada y con datos. */
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
            ];
            return Respuesta::json($datos);
        } catch (Throwable) {
            return Respuesta::json(['estado' => 'sin base de datos'], 503);
        }
    }
}
