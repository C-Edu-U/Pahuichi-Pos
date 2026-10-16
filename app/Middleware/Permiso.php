<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Middleware;
use App\Core\Peticion;
use App\Core\Respuesta;
use App\Core\Vista;
use App\Models\Bitacora;
use App\Models\Usuario;

/**
 * «permiso:modulo.usuarios»: si el rol no lo tiene, responde «acceso denegado»
 * sin ningún dato del módulo y deja el intento en la bitácora (HU-04).
 * Va siempre después de «auth».
 */
final class Permiso implements Middleware
{
    public function __construct(private readonly string $permiso)
    {
    }

    public function manejar(Peticion $peticion, callable $siguiente): Respuesta
    {
        if (puede($this->permiso)) {
            return $siguiente($peticion);
        }

        Bitacora::registrar('acceso.denegado', null, null, [
            'ruta' => $peticion->ruta,
            'metodo' => $peticion->metodo,
            'permiso' => $this->permiso,
        ]);

        if ($peticion->esperaJson()) {
            return Respuesta::json(['error' => 'Tu rol no tiene permiso para esta acción.'], 403);
        }

        $clave = str_starts_with($this->permiso, 'modulo.') ? substr($this->permiso, 7) : null;
        $modulo = $clave !== null ? (Config::get('modulos')[$clave] ?? null) : null;
        $html = Vista::render('errores/403', [
            'titulo' => 'Acceso denegado',
            'h1' => 'Acceso denegado',
            'modulo' => '',
            'nombreModulo' => $modulo['texto'] ?? null,
            'rol' => Usuario::ROLES[Auth::rol()] ?? '',
        ]);
        return Respuesta::html($html, 403);
    }
}
