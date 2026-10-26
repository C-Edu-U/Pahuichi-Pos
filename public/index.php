<?php

declare(strict_types=1);

// Front controller: toda petición que no es un archivo de public/ entra por aquí.
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\App;
use App\Core\Peticion;
use App\Core\Sesion;

App::preparar();
Sesion::iniciar();
App::crear()->manejar(Peticion::desdeGlobales())->enviar();
