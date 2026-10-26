<?php

declare(strict_types=1);
namespace App\Services;

use App\Core\App;
use DomainException;

/** Estado de respaldos y solicitudes acotadas al contenedor; la web no ejecuta comandos. */
final class RespaldosService
{
    private string $carpeta;
    private string $interno;
    public function __construct(?string $carpeta = null, ?string $interno = null)
    {
        $this->carpeta = $carpeta ?? App::raiz() . '/respaldos';
        $this->interno = $interno ?? ($carpeta === null ? App::raiz() . '/var/respaldos' : $carpeta);
    }
    private static function nombreValido(string $nombre): bool
    {
        return (bool) preg_match('/^pahuichi-\d{4}-\d{2}-\d{2}_\d{6}\.[A-Za-z0-9]{6}\.sql$/D', $nombre);
    }
    public function estado(string $solicitud = ''): array
    {
        clearstatcache();
        $ahora = time(); $copias = [];
        foreach (glob($this->carpeta . '/pahuichi-*.sql') ?: [] as $ruta) {
            $nombre = basename($ruta);
            if (!self::nombreValido($nombre) || is_link($ruta) || !is_file($ruta)) continue;
            $peso = @filesize($ruta); $fecha = @filemtime($ruta);
            if ($peso === false || $peso === 0 || $fecha === false) continue;
            $copias[] = ['nombre' => $nombre, 'fecha' => substr($nombre, 9, 10) . ' ' . substr($nombre, 20, 2) . ':' . substr($nombre, 22, 2) . ':' . substr($nombre, 24, 2), 'peso' => $peso, 'vence' => $fecha + 30 * 86400];
        }
        usort($copias, static fn (array $a, array $b): int => strcmp($b['nombre'], $a['nombre']));
        $latido = $this->leer($this->interno . '/.estado.json');
        $activo = isset($latido['ahora']) && $ahora - (int) $latido['ahora'] >= 0 && $ahora - (int) $latido['ahora'] <= 15;
        $pendiente = null;
        foreach (glob($this->interno . '/.solicitudes/*.req') ?: [] as $ruta) {
            $id = basename($ruta, '.req');
            if (!preg_match('/^[a-f0-9]{32}$/D', $id) || is_link($ruta)) continue;
            $lineas = @file($ruta, FILE_IGNORE_NEW_LINES);
            if ($lineas === false || count($lineas) !== 3) continue;
            $pendiente = ['id' => $id, 'accion' => $lineas[0], 'archivo' => $lineas[1], 'programada' => (int) $lineas[2], 'estado' => 'pendiente'];
            $avance = $this->leer($this->interno . '/.solicitudes/' . $id . '.json');
            if ($avance && in_array($avance['estado'] ?? '', ['hecho', 'error'], true)) { $pendiente = null; continue; }
            if ($avance) $pendiente = $avance + $pendiente;
            break;
        }
        $resultado = null;
        if ($solicitud !== '' && preg_match('/^[a-f0-9]{32}$/D', $solicitud)) {
            $resultado = $this->leer($this->interno . '/.solicitudes/' . $solicitud . '.json');
            if (!$resultado && $pendiente && $pendiente['id'] === $solicitud) $resultado = $pendiente;
        }
        return ['ahora' => $ahora, 'activo' => $activo, 'hora' => $latido['hora'] ?? (getenv('BACKUP_HORA') ?: '02:00'), 'proxima' => (int) ($latido['proxima'] ?? 0), 'copias' => array_slice($copias, 0, 10), 'pendiente' => $pendiente, 'resultado' => $resultado, 'restauracion' => $this->leer($this->interno . '/.restauracion.json'), 'esRestaurada' => getenv('APP_RESTAURADA') === '1', 'puertoRestaurado' => (int) (getenv('PUERTO_RESTAURACION') ?: 8445)];
    }
    public function solicitar(string $accion, string $archivo): array
    {
        if (getenv('APP_RESTAURADA') === '1') throw new DomainException('Las pruebas de copias se realizan desde el sistema habitual.');
        if (!in_array($accion, ['copiar', 'vencer', 'restaurar'], true)) throw new DomainException('Acción de simulación no válida.');
        if ($accion !== 'restaurar' && !con_demostraciones()) throw new DomainException('Las simulaciones de copias no están disponibles en producción.');
        $estado = $this->estado();
        if (!$estado['activo']) throw new DomainException('El servicio de copias no está disponible. Espera unos segundos o revisa Docker.');
        if ($accion !== 'copiar' && (!self::nombreValido($archivo) || is_link($this->carpeta . '/' . $archivo) || !is_file($this->carpeta . '/' . $archivo))) throw new DomainException('La copia seleccionada ya no está disponible.');
        if ($accion === 'copiar') $archivo = '';
        $directorio = $this->interno . '/.solicitudes';
        $lock = fopen($directorio . '/.crear.lock', 'c');
        if ($lock === false) throw new DomainException('No se pudo preparar la simulación.');
        try {
            if (!flock($lock, LOCK_EX)) throw new DomainException('No se pudo preparar la simulación.');
            $pendiente = $this->estado()['pendiente'];
            if ($pendiente) {
                if ($pendiente['accion'] === $accion && $pendiente['archivo'] === $archivo) return $pendiente;
                throw new DomainException('Espera a que termine la simulación en curso.');
            }
            $id = bin2hex(random_bytes(16)); $programada = time() + ($accion === 'restaurar' ? 1 : 5);
            $temporal = tempnam($directorio, '.preparando-');
            if ($temporal === false) throw new DomainException('No se pudo preparar la simulación.');
            try {
                if (file_put_contents($temporal, $accion . "\n" . $archivo . "\n" . $programada . "\n", LOCK_EX) === false || !rename($temporal, $directorio . '/' . $id . '.req')) throw new DomainException('No se pudo guardar la simulación.');
            } finally { if (is_file($temporal)) unlink($temporal); }
            return ['id' => $id, 'accion' => $accion, 'archivo' => $archivo, 'programada' => $programada, 'estado' => 'pendiente'];
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }
    private function leer(string $ruta): ?array
    {
        if (!is_file($ruta) || is_link($ruta)) return null;
        $s = @file_get_contents($ruta);
        if ($s === false) return null;
        $datos = json_decode($s, true);
        return is_array($datos) ? $datos : null;
    }
}
