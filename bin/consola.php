<?php

declare(strict_types=1);

/**
 * Tareas de mantenimiento. Dentro del contenedor:
 *   php bin/consola.php migrar      aplica las migraciones pendientes y, si la base está vacía, los datos de prueba
 *   php bin/consola.php reiniciar   borra todo y vuelve a cargar los datos de prueba
 *   php bin/consola.php esperar-db  espera a que MariaDB acepte conexiones (lo usa el arranque)
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\App;
use App\Core\Config;
use App\Core\DB;
use App\Core\Migrador;

App::preparar();
$comando = $argv[1] ?? 'ayuda';
$carpeta = App::raiz() . '/database';

function mensaje(string $texto): void
{
    fwrite(STDOUT, "Pahuichi: {$texto}\n");
}

switch ($comando) {
    case 'esperar-db':
        for ($i = 0; $i < 60; $i++) {
            try {
                DB::conectar(Config::get('app.db'));
                exit(0);
            } catch (PDOException) {
                sleep(1);
            }
        }
        mensaje('la base de datos no responde.');
        exit(1);

    case 'migrar':
        $migrador = new Migrador(DB::conexion(), $carpeta);
        $aplicadas = $migrador->migrar();
        mensaje($aplicadas ? 'migraciones aplicadas: ' . implode(', ', $aplicadas) : 'la base está al día.');
        if ($migrador->sembrarSiVacia()) {
            mensaje('datos de prueba cargados.');
        }
        exit(0);

    case 'reiniciar':
        $migrador = new Migrador(DB::conexion(), $carpeta);
        $migrador->vaciar();
        $migrador->migrar();
        $migrador->sembrarSiVacia();
        mensaje('base reiniciada con los datos de prueba.');
        exit(0);

    default:
        fwrite(STDOUT, "Uso: php bin/consola.php migrar | reiniciar | esperar-db\n");
        exit($comando === 'ayuda' ? 0 : 1);
}
