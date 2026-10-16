<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Rutas: método + patrón → [Controlador, método] y su lista de middleware.
 * Los segmentos {id} aceptan solo números; {clave} acepta letras, números y guion.
 */
final class Router
{
    private array $rutas = [];

    public function get(string $patron, array $accion, array $middleware = []): void
    {
        $this->agregar('GET', $patron, $accion, $middleware);
    }

    public function post(string $patron, array $accion, array $middleware = []): void
    {
        $this->agregar('POST', $patron, $accion, $middleware);
    }

    private function agregar(string $metodo, string $patron, array $accion, array $middleware): void
    {
        $regex = preg_replace_callback('/\{(\w+)\}/', static function (array $m): string {
            $tipo = $m[1] === 'id' ? '\d+' : '[a-z0-9-]+';
            return "(?P<{$m[1]}>{$tipo})";
        }, rtrim($patron, '/') ?: '/');
        $this->rutas[] = [$metodo, '#^' . $regex . '$#', $accion, $middleware];
    }

    /**
     * @return array{0: int, 1: ?array, 2: array, 3: array} estado (200, 404 o 405), acción, middleware, parámetros
     */
    public function resolver(string $metodo, string $ruta): array
    {
        $otroMetodo = false;
        foreach ($this->rutas as [$m, $regex, $accion, $middleware]) {
            if (!preg_match($regex, $ruta, $coincide)) {
                continue;
            }
            if ($m !== $metodo && !($metodo === 'HEAD' && $m === 'GET')) {
                $otroMetodo = true;
                continue;
            }
            $parametros = array_filter($coincide, 'is_string', ARRAY_FILTER_USE_KEY);
            return [200, $accion, $middleware, $parametros];
        }
        return [$otroMetodo ? 405 : 404, null, [], []];
    }
}
