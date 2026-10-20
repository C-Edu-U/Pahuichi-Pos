<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

/**
 * Convierte un registro de la bitácora en texto legible (HU-05): nombre de la
 * acción, entidad afectada y detalle con el valor anterior y el nuevo.
 * El catálogo de acciones está en config/bitacora.php.
 */
final class BitacoraTexto
{
    /** @return array{0: string, 1: string} texto y tono (ok, aviso, peligro, neutra) */
    public static function accion(string $accion): array
    {
        $a = Config::get('bitacora')['acciones'][$accion] ?? null;
        return $a === null ? [$accion, 'neutra'] : [$a[0], $a[2]];
    }

    /**
     * Acciones que corresponden a un valor del filtro: «sensibles», «grupo:caja»
     * o el nombre exacto de una acción. Devuelve null si el valor no es válido.
     *
     * @return string[]|null
     */
    public static function accionesDeFiltro(string $valor): ?array
    {
        $c = Config::get('bitacora');
        if ($valor === 'sensibles') {
            return $c['sensibles'];
        }
        if (str_starts_with($valor, 'grupo:')) {
            $grupo = substr($valor, 6);
            if (!isset($c['grupos'][$grupo])) {
                return null;
            }
            return array_keys(array_filter($c['acciones'], static fn (array $a): bool => $a[1] === $grupo));
        }
        return preg_match('/^[a-z_]+(\.[a-z_]+)+$/', $valor) ? [$valor] : null;
    }

    /** Opciones del filtro de acción, agrupadas: [grupo => [valor => texto]]. */
    public static function opcionesFiltro(): array
    {
        $c = Config::get('bitacora');
        $opciones = [];
        foreach ($c['grupos'] as $clave => $nombre) {
            $opciones[$nombre] = ["grupo:{$clave}" => "Todo: {$nombre}"];
        }
        foreach ($c['acciones'] as $accion => [$texto, $grupo]) {
            $opciones[$c['grupos'][$grupo]][$accion] = $texto;
        }
        return $opciones;
    }

    /** «Usuario: Luis Fernando Roca», «Producto: PL-01 · Majadito», «Caja: Medio día 15/10». */
    public static function entidad(array $f): string
    {
        $id = $f['entidad_id'];
        // Si la entidad es el mismo usuario que actuó (inicio de sesión, cambio de clave), no se repite
        if ($f['entidad'] === 'usuarios' && $id !== null && (string) $id === (string) $f['usuario_id']) {
            return '';
        }
        return match ($f['entidad']) {
            null, '' => '',
            'usuarios' => 'Usuario: ' . ($f['entidad_usuario'] ?? "#{$id}"),
            'productos' => 'Producto: ' . ($f['entidad_codigo'] !== null ? "{$f['entidad_codigo']} · {$f['entidad_producto']}" : "#{$id}"),
            'categorias' => 'Categoría: ' . ($f['entidad_categoria'] ?? "#{$id}"),
            'cajas' => 'Caja: ' . ($f['entidad_caja_turno'] !== null
                ? $f['entidad_caja_turno'] . ' ' . date('d/m', strtotime($f['entidad_caja_fecha']))
                : ($id !== null ? "#{$id}" : 'varias')),
            'negocio' => 'Datos del negocio',
            'parametros_fiscales' => 'Parámetros fiscales',
            // El nombre que se guardó en el momento de la acción: el turno puede estar borrado
            'turnos' => 'Turno: ' . (self::nombreDe($f) ?? ($id !== null ? "#{$id}" : 'horario')),
            default => $f['entidad'] . ($id !== null ? " #{$id}" : ''),
        };
    }

    /**
     * Nombre de la entidad en el momento de la acción: primero el que quedó
     * guardado en la fila, después el que está en la base ahora, y al final el
     * que aparece en el detalle (sirve para las filas escritas antes del
     * nombre guardado).
     */
    private static function nombreDe(array $f): ?string
    {
        $guardado = $f['entidad_nombre'] ?? null;
        if (is_string($guardado) && $guardado !== '') {
            return $guardado;
        }
        $actual = $f['entidad_turno'] ?? null;
        if (is_string($actual) && $actual !== '') {
            return $actual;
        }
        $detalle = json_decode((string) ($f['detalle'] ?? ''), true);
        $nombre = is_array($detalle) ? ($detalle['nombre'] ?? null) : null;
        return is_string($nombre) && $nombre !== '' ? $nombre : null;
    }

    /** Detalle en una línea: «Precio: Bs 40,00 → Bs 42,00 · Código: PL-01». */
    public static function detalle(?string $json): string
    {
        $d = $json === null ? null : json_decode($json, true);
        if (!is_array($d) || $d === []) {
            return '';
        }

        $partes = [];
        // Cambio simple: {"antes": x, "despues": y, ...otros datos}
        if (array_key_exists('antes', $d) && array_key_exists('despues', $d)) {
            $antes = $d['antes'];
            $despues = $d['despues'];
            unset($d['antes'], $d['despues']);
            if (is_array($antes) || is_array($despues)) {
                foreach (array_unique([...array_keys((array) $antes), ...array_keys((array) $despues)]) as $campo) {
                    // Se comparan ya formateados: 2 y "2" son el mismo valor
                    $a = self::valor($antes[$campo] ?? null);
                    $b = self::valor($despues[$campo] ?? null);
                    if ($a !== $b) {
                        $partes[] = self::campo((string) $campo) . ": {$a} → {$b}";
                    }
                }
            } else {
                $partes[] = self::valor($antes) . ' → ' . self::valor($despues);
            }
        }

        foreach ($d as $campo => $valor) {
            if (is_array($valor) && array_key_exists('antes', $valor) && array_key_exists('despues', $valor)) {
                $partes[] = self::campo((string) $campo) . ': ' . self::valor($valor['antes']) . ' → ' . self::valor($valor['despues']);
            } else {
                $partes[] = self::campo((string) $campo) . ': ' . self::valor($valor);
            }
        }
        return implode(' · ', $partes);
    }

    private static function campo(string $campo): string
    {
        return Config::get('bitacora')['campos'][$campo] ?? ucfirst(str_replace('_', ' ', $campo));
    }

    private static function valor(mixed $v): string
    {
        return match (true) {
            $v === null || $v === '' => '(vacío)',
            is_bool($v) => $v ? 'sí' : 'no',
            is_array($v) => (string) json_encode($v, JSON_UNESCAPED_UNICODE),
            is_string($v) && preg_match('/^\d+\.\d{2}$/', $v) === 1 => 'Bs ' . bs($v),
            $v === 'pin' => 'PIN',
            $v === 'clave' => 'contraseña',
            default => (string) $v,
        };
    }
}
