<?php
/** QA HU-57. Reinicia EXCLUSIVAMENTE la base <DB_NOMBRE>_pruebas, igual que PHPUnit.
 * Ejecutar: php tests/hu57.php. Requiere proc_open para las dos conexiones simultáneas.
 */
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\{App, Auth, Config, Csrf, DB, Migrador, Peticion, Reloj, Sesion};
use App\Models\{Factura, Usuario};
use App\Services\{CajaService, VentaService};

App::preparar();
$c = Config::get('app.db');
$c['nombre'] .= '_pruebas';
Config::fijar('app.db', $c);
DB::usar(DB::conectar($c));
Sesion::enMemoria();
Reloj::fijar('2026-10-20 12:00:00');

if (($argv[1] ?? '') === '--worker') {
    Auth::iniciar(Usuario::buscarPorUsuario('mariela'), 'pin');
    $pedido = json_decode((string) fgets(STDIN), true, 32, JSON_THROW_ON_ERROR);
    $f = VentaService::cobrar($pedido);
    echo json_encode(['numero' => (int) $f['numero'], 'id' => (int) $f['id'], 'cuf' => $f['cuf']], JSON_THROW_ON_ERROR);
    exit;
}
function comprobar(bool $ok, string $nombre): void {
    if (!$ok) throw new RuntimeException('FALLO: ' . $nombre);
    echo "OK: {$nombre}\n";
}
function uuid(): string { return bin2hex(random_bytes(4)) . '-' . bin2hex(random_bytes(2)) . '-4' . bin2hex(random_bytes(2))[0] . substr(bin2hex(random_bytes(2)), 0, 2) . '-a' . substr(bin2hex(random_bytes(2)), 0, 3) . '-' . bin2hex(random_bytes(6)); }
function enviar(array $d): \App\Core\Respuesta {
    $d['lineas'] = json_encode($d['lineas']);
    $d['_csrf'] = Csrf::token();
    return App::crear()->manejar(new Peticion('POST', '/pos/cobrar', [], $d));
}

// Nunca cambia DB_NOMBRE ni se conecta a la base de trabajo para borrar tablas.
echo 'Base exclusiva de pruebas: ' . $c['nombre'] . "\n";
$m = new Migrador(DB::conexion(), App::raiz() . '/database');
$m->vaciar(); $m->migrar(); $m->sembrarSiVacia();
Auth::iniciar(Usuario::buscarPorUsuario('mariela'), 'pin');
$u = Auth::usuario();
$turno = (int) DB::valor('SELECT id FROM turnos WHERE activo = 1 ORDER BY id LIMIT 1');
$caja = (new CajaService())->abrir($turno, (int) $u['id'], 0, $u);
$p = DB::fila('SELECT p.* FROM productos p JOIN categorias c ON c.id=p.categoria_id WHERE p.activo=1 AND c.activo=1 AND p.agotado=0 ORDER BY p.id LIMIT 1');
$d = ['uuid' => uuid(), 'caja_id' => $caja, 'tipo' => 'llevar', 'mesa' => '', 'exacto' => '1',
    'total_esperado' => (string) $p['precio'], 'lineas' => [['id' => (int) $p['id'], 'cantidad' => 1, 'nota' => '']]];
DB::consulta('UPDATE parametros_fiscales SET numero_inicial = 100, codigo_sucursal = 0, codigo_punto_venta = 0');
$r = enviar($d);
comprobar($r->estado === 200, 'Cobro por ruta MVC con CSRF: ' . ($r->estado === 200 ? '200' : $r->cuerpo));
$f1 = Factura::buscar((int) json_decode($r->cuerpo, true)['id']);
comprobar((int) $f1['numero'] === 100 && preg_match('/^[A-F0-9]{28}$/D', $f1['cuf']) === 1, 'Número inicial y CUF de 28 hexadecimales mayúsculos');
$f2 = VentaService::cobrar($d);
comprobar($f1['id'] === $f2['id'] && (int) DB::valor('SELECT COUNT(*) FROM pagos') === 1, 'Reintento del mismo UUID no duplica venta ni pago');
$pagina = App::crear()->manejar(new Peticion('GET', '/facturas/' . $f1['id']));
comprobar($pagina->estado === 200 && str_contains($pagina->cuerpo, $f1['cuf']), 'Consulta de factura MVC desde snapshot');
DB::consulta("UPDATE productos SET nombre = CONCAT(nombre, ' QA'), precio = precio + 1 WHERE id = ?", [$p['id']]);
$fLeida = Factura::buscar((int) $f1['id']);
comprobar($fLeida['snapshot_json'] === $f1['snapshot_json'], 'Recargar conserva precio, descripción, CUF, número y leyenda');
DB::consulta('UPDATE productos SET nombre=?, precio=? WHERE id=?', [$p['nombre'], $p['precio'], $p['id']]);
// Simula el esquema anterior de HU-10 y vuelve a aplicar la migración sin perder la factura.
DB::consulta('ALTER TABLE facturas DROP INDEX uq_factura_numero, ADD UNIQUE KEY uq_factura_numero (punto_venta, numero), DROP COLUMN sucursal');
DB::consulta('ALTER TABLE correlativos DROP PRIMARY KEY, DROP COLUMN sucursal, ADD PRIMARY KEY (punto_venta)');
DB::consulta("DELETE FROM migraciones WHERE archivo = '057_hu57_correlativos.sql'");
$m->migrar();
comprobar(Factura::buscar((int)$f1['id'])['snapshot_json'] === $f1['snapshot_json'], 'Migración desde esquema HU-10 conserva la factura existente');
$sinCsrf=$d; $sinCsrf['lineas']=json_encode($sinCsrf['lineas']); $sinCsrf['_csrf']='incorrecto';
$rSinCsrf=App::crear()->manejar(new Peticion('POST','/pos/cobrar',[],$sinCsrf));
comprobar($rSinCsrf->estado === 403 && (int)DB::valor('SELECT COUNT(*) FROM ventas') === 1, 'Cobro sin CSRF válido rechazado');
// No admite consumir numeración fuera de una transacción.
try { Factura::siguientes(0, 0, 100); comprobar(false, 'Debe exigir transacción'); }
catch (LogicException) { comprobar(true, 'Correlativo exige una transacción'); }
// Un error después de asignar número debe revertir venta, pago y contador.
DB::consulta("CREATE TRIGGER qa_hu57_fallo BEFORE INSERT ON facturas FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'QA fallo forzado'");
$d['uuid'] = uuid();
try { VentaService::cobrar($d); comprobar(false, 'Debe fallar la inserción'); }
catch (PDOException) { comprobar((int) DB::valor('SELECT ultimo_numero FROM correlativos WHERE sucursal=0 AND punto_venta=0') === 100 && (int) DB::valor('SELECT COUNT(*) FROM ventas') === 1 && (int) DB::valor('SELECT COUNT(*) FROM pagos') === 1, 'Rollback tras fallo de factura no consume número ni deja venta/pago'); }
finally { DB::consulta('DROP TRIGGER qa_hu57_fallo'); }
// Dos procesos independientes cobran al mismo tiempo, con conexiones separadas.
$procesos = [];
for ($i=0; $i<2; $i++) {
    $proc = proc_open([PHP_BINARY, __FILE__, '--worker'], [['pipe','r'], ['pipe','w'], ['pipe','w']], $pipes);
    if (!is_resource($proc)) throw new RuntimeException('No se pudo iniciar el proceso concurrente.');
    $procesos[] = [$proc, $pipes];
}
foreach ($procesos as [$proc, $pipes]) { $d['uuid']=uuid(); fwrite($pipes[0], json_encode($d) . "\n"); fclose($pipes[0]); }
$numeros=[]; $cufs=[];
foreach ($procesos as [$proc, $pipes]) {
    $salida=stream_get_contents($pipes[1]); $error=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    comprobar(proc_close($proc) === 0, 'Proceso concurrente terminó correctamente: ' . $error);
    $f=json_decode($salida,true,32,JSON_THROW_ON_ERROR); $numeros[]=$f['numero']; $cufs[]=$f['cuf'];
}
sort($numeros);
comprobar($numeros === [101,102] && count(array_unique($cufs)) === 2, 'Dos cobros simultáneos: 101 y 102, CUF distintos, sin saltos');
// El número inicial no reinicia una secuencia ya utilizada.
DB::consulta('UPDATE parametros_fiscales SET numero_inicial=800');
$d['uuid']=uuid(); $f=VentaService::cobrar($d);
comprobar((int)$f['numero'] === 103, 'Cambiar número inicial no provoca salto en secuencia iniciada');
DB::consulta('UPDATE parametros_fiscales SET codigo_punto_venta=1, numero_inicial=20');
$d['uuid']=uuid(); $f=VentaService::cobrar($d);
comprobar((int)$f['numero'] === 20, 'Otro punto de venta inicia su propia secuencia');
DB::consulta('UPDATE parametros_fiscales SET codigo_sucursal=1');
$d['uuid']=uuid(); $f=VentaService::cobrar($d);
comprobar((int)$f['numero'] === 20, 'Otra sucursal puede usar el mismo número sin duplicar su serie');
$antes=(int)DB::valor('SELECT COUNT(*) FROM facturas');
$d['uuid']=uuid(); $d['exacto']='0'; $d['recibido']='0.01';
comprobar(enviar($d)->estado === 422 && (int)DB::valor('SELECT COUNT(*) FROM facturas') === $antes, 'Efectivo insuficiente rechazado sin factura');
Auth::iniciar(Usuario::buscarPorUsuario('jorge'),'pin');
comprobar(App::crear()->manejar(new Peticion('GET','/facturas/'.$f1['id']))->estado === 403, 'Otro cajero no consulta la factura ajena');
comprobar((int) DB::valor('SELECT COUNT(*) FROM facturas WHERE CHAR_LENGTH(cuf) <> 28 OR BINARY cuf NOT REGEXP \'^[A-F0-9]{28}$\'') === 0, 'Todas las facturas cumplen formato CUF');
echo "HU-57: pruebas terminadas. La base de trabajo no se modificó.\n";
