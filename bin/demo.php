<?php

declare(strict_types=1);

/** HU-65. Solo consola y exclusivamente en la base aislada de demostración. */
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\{App, Config, DB, Migrador};
use App\Services\FotoProducto;

App::preparar();
$bloqueado = false;
try {
    if (PHP_SAPI !== 'cli' || getenv('APP_DEMO_HU65') !== '1'
        || Config::get('app.db.nombre') !== 'pahuichi_demo_hu65') {
        throw new RuntimeException('La carga requiere APP_DEMO_HU65=1 y la base separada pahuichi_demo_hu65.');
    }
    $accion = $argv[1] ?? 'cargar';
    if (!in_array($accion, ['cargar', 'resumen', 'huella'], true)) {
        throw new RuntimeException('Uso: php bin/demo.php cargar | resumen | huella');
    }
    $pdo = DB::conexion();
    if (DB::valor('SELECT DATABASE()') !== 'pahuichi_demo_hu65') {
        throw new RuntimeException('La conexión no corresponde a la base de demostración.');
    }
    if ($accion === 'huella') {
        // Incluye filas completas, no solo cantidades: detecta modificaciones al repetir la carga.
        $huella = hash_init('sha256');
        $tablas = DB::filas("SELECT TABLE_NAME AS nombre FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME");
        foreach ($tablas as $t) {
            $nombre = str_replace('`', '``', $t['nombre']);
            $filas = array_map(static fn (array $r): string => serialize($r), DB::filas("SELECT * FROM `$nombre`"));
            sort($filas, SORT_STRING);
            hash_update($huella, $nombre . serialize($filas));
        }
        echo hash_final($huella) . PHP_EOL;
        exit(0);
    }
    if ($accion === 'cargar') {
        $base = App::raiz() . '/database/demo';
        $sql = file_get_contents($base . '/datos.sql');
        $productos = json_decode((string) file_get_contents($base . '/productos.json'), true, 512, JSON_THROW_ON_ERROR);
        if (!is_string($sql) || $sql === '' || !is_array($productos) || count($productos) !== 25) {
            throw new RuntimeException('El paquete de demostración está incompleto.');
        }
        // Validar y comprimir todas las imágenes antes de escribir datos.
        $fotos = [];
        foreach ($productos as $producto) {
            $archivo = $producto['archivo'];
            if (!is_string($archivo) || basename($archivo) !== $archivo || !is_file($base . '/fotos/' . $archivo)) {
                throw new RuntimeException('Falta una foto de demostración.');
            }
            $bytes = (string) file_get_contents($base . '/fotos/' . $archivo);
            $nombre = 'foto-' . substr(hash('sha256', $bytes), 0, 16) . '.jpg';
            $fotos[$nombre] = FotoProducto::comprimir($bytes);
        }
        $version = hash('sha256', $sql . json_encode($productos, JSON_THROW_ON_ERROR));
        if ((int) DB::valor("SELECT GET_LOCK('pahuichi_demo_hu65.carga', 60)") !== 1) {
            throw new RuntimeException('Hay otra carga en ejecución. Inténtalo de nuevo.');
        }
        $bloqueado = true;
        $migrador = new Migrador($pdo, App::raiz() . '/database');
        $migrador->migrar(); // No llama al sembrado habitual: el SQL contiene los usuarios y el catálogo de la demo.
        DB::consulta('CREATE TABLE IF NOT EXISTS demo_hu65 (
            id TINYINT UNSIGNED PRIMARY KEY,
            version CHAR(64) NOT NULL,
            cargada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB');
        $previa = DB::valor('SELECT version FROM demo_hu65 WHERE id=1');
        if ($previa !== null && !hash_equals($version, (string) $previa)) {
            throw new RuntimeException('Esta demo fue cargada con otra versión. Se conservaron sus datos; usa una base demo nueva.');
        }
        if ($previa === null) {
            foreach (['usuarios', 'negocio', 'productos', 'categorias', 'promociones', 'ventas', 'cajas', 'facturas'] as $tabla) {
                if ((int) DB::valor("SELECT COUNT(*) FROM `$tabla`") !== 0) {
                    throw new RuntimeException('La base demo ya contiene datos ajenos a esta carga. No se borró ningún registro.');
                }
            }
        }
        $subidas = Config::get('app.subidas');
        if (!is_dir($subidas) && !mkdir($subidas, 0775, true) && !is_dir($subidas)) {
            throw new RuntimeException('No se pudo preparar la carpeta de fotos.');
        }
        foreach ($fotos as $nombre => $jpeg) {
            $destino = $subidas . '/' . $nombre;
            if (is_file($destino) && hash_file('sha256', $destino) === hash('sha256', $jpeg)) continue;
            $temporal = tempnam($subidas, 'hu65-');
            if ($temporal === false) throw new RuntimeException('No se pudo preparar una foto.');
            try {
                if (file_put_contents($temporal, $jpeg, LOCK_EX) !== strlen($jpeg)
                    || !chmod($temporal, 0644) || !rename($temporal, $destino)) {
                    throw new RuntimeException('No se pudo guardar una foto de la demo.');
                }
            } finally {
                if (is_file($temporal)) unlink($temporal);
            }
        }
        if ($previa === null) {
            DB::transaccion(static function () use ($sql, $version): void {
                foreach (Migrador::sentencias($sql) as $sentencia) DB::conexion()->exec($sentencia);
                foreach (['productos' => 25, 'promociones' => 5, 'ventas' => 40, 'facturas' => 40, 'cajas' => 2, 'arqueos' => 2] as $tabla => $cantidad) {
                    if ((int) DB::valor("SELECT COUNT(*) FROM `$tabla`") !== $cantidad) {
                        throw new RuntimeException("La carga de $tabla está incompleta. Se deshizo la carga de datos.");
                    }
                }
                DB::consulta('INSERT INTO demo_hu65 (id,version) VALUES (1,?)', [$version]);
            });
            echo "HU-65 cargada: 25 productos con foto, 5 promociones y 40 ventas en 2 turnos cerrados.\n";
        } else {
            echo "HU-65 ya estaba cargada. Registros nuevos: 0. Se conservaron ventas y cambios posteriores.\n";
        }
    }
    $resumen = [
        'productos' => (int) DB::valor('SELECT COUNT(*) FROM productos'),
        'productos_con_foto' => (int) DB::valor("SELECT COUNT(*) FROM productos WHERE foto IS NOT NULL AND foto<>''"),
        'promociones' => (int) DB::valor('SELECT COUNT(*) FROM promociones'),
        'ventas' => (int) DB::valor('SELECT COUNT(*) FROM ventas'),
        'facturas' => (int) DB::valor('SELECT COUNT(*) FROM facturas'),
        'turnos' => DB::filas('SELECT c.fecha,t.nombre AS turno,u.usuario AS cajero,c.estado,a.efectivo_esperado,a.total_contado,a.diferencia FROM cajas c JOIN turnos t ON t.id=c.turno_id JOIN usuarios u ON u.id=c.cajero_id JOIN arqueos a ON a.caja_id=c.id WHERE a.reemplazado_en IS NULL ORDER BY c.id'),
    ];
    echo json_encode($resumen, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'HU-65: ' . $e->getMessage() . PHP_EOL);
    exit(1);
} finally {
    if ($bloqueado) DB::consulta("SELECT RELEASE_LOCK('pahuichi_demo_hu65.carga')");
}
