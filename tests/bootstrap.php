<?php

declare(strict_types=1);

// Las pruebas usan la base <nombre>_pruebas (la crea docker/db/init), nunca la de trabajo.
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\App;
use App\Core\Config;
use App\Core\DB;
use App\Core\Migrador;
use App\Core\Sesion;

App::preparar();
Sesion::enMemoria();

$c = Config::get('app.db');
$c['nombre'] .= '_pruebas';
Config::fijar('app.db', $c);

$pdo = DB::conectar($c);
DB::usar($pdo);

$migrador = new Migrador($pdo, App::raiz() . '/database');
$migrador->vaciar();
$migrador->migrar();
$migrador->sembrarSiVacia();
