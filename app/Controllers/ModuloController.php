<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\ErrorHttp;
use App\Core\Peticion;
use App\Core\Respuesta;

/** Pantalla «próximamente» de los módulos que aún no tienen su historia implementada. */
final class ModuloController extends Controller
{
    public function pendiente(Peticion $peticion): Respuesta
    {
        foreach (Config::get('modulos') as $clave => $m) {
            if ($m['ruta'] === $peticion->ruta && isset($m['pendiente'])) {
                return $this->vista('modulos/pendiente', [
                    'titulo' => $m['texto'],
                    'modulo' => $clave,
                    'h1' => $m['texto'],
                    'm' => $m,
                ]);
            }
        }
        throw ErrorHttp::noEncontrado();
    }
}
