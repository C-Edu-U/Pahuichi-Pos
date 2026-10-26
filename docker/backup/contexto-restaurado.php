<?php
/** Se carga solo en la app de prueba; la app habitual no usa este archivo. */
$marcador = dirname(__DIR__, 2) . '/var/respaldos/.restauracion.json';
// dirname(__DIR__, 2) es la raiz del proyecto: docker/backup -> raiz.
$restauracionActiva = is_file($marcador) ? json_decode((string) file_get_contents($marcador), true) : null;
$nombreRestaurado = is_array($restauracionActiva) ? (string) ($restauracionActiva['base'] ?? '') : '';
if (!preg_match('/^pahuichi_hu07_demo_[a-f0-9]{32}$/D', $nombreRestaurado) || $nombreRestaurado === getenv('DB_HABITUAL')) {
    http_response_code(503);
    exit('Todavía no hay una restauración disponible. Inicia la prueba desde Copias de seguridad.');
}
putenv('DB_NOMBRE=' . $nombreRestaurado);
// Una cookie propia impide mezclar la sesion habitual y las sesiones de cada prueba.
if (session_status() !== PHP_SESSION_ACTIVE) session_name('PAHUICHI_RESTAURADA_' . substr(hash('sha256', $nombreRestaurado), 0, 12));
