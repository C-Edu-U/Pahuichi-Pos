<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected function vista(string $vista, array $datos = [], int $estado = 200, ?string $layout = 'layouts/panel'): Respuesta
    {
        return Respuesta::html(Vista::render($vista, $datos, $layout), $estado);
    }

    protected function redirigir(string $ruta): Respuesta
    {
        return Respuesta::redirigir($ruta);
    }

    /** Redirige con un aviso breve que la página siguiente muestra una vez. */
    protected function redirigirConAviso(string $ruta, string $aviso): Respuesta
    {
        Sesion::flash('aviso', $aviso);
        return Respuesta::redirigir($ruta);
    }
}
