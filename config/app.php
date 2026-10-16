<?php

declare(strict_types=1);

// Configuración general. Las credenciales llegan por variables de entorno (docker-compose.yml).
return [
    'nombre' => 'Pahuichi POS',
    'entorno' => getenv('APP_ENTORNO') ?: 'desarrollo',
    'zona_horaria' => 'America/La_Paz',

    'db' => [
        'host' => getenv('DB_HOST') ?: 'db',
        'puerto' => (int) (getenv('DB_PUERTO') ?: 3306),
        'nombre' => getenv('DB_NOMBRE') ?: 'pahuichi',
        'usuario' => getenv('DB_USUARIO') ?: 'pahuichi',
        'clave' => getenv('DB_CLAVE') ?: 'pahuichi_local',
    ],

    // Seguridad de acceso (RNF-16, RNF-18)
    'sesion' => [
        'inactividad_min' => 15,
        'intentos_max' => 5,
        'bloqueo_min' => 15,
    ],

    'subidas' => dirname(__DIR__) . '/storage/subidas',
];
