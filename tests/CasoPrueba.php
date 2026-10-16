<?php

declare(strict_types=1);

namespace Tests;

use App\Core\App;
use App\Core\Csrf;
use App\Core\DB;
use App\Core\Peticion;
use App\Core\Reloj;
use App\Core\Respuesta;
use App\Core\Sesion;
use PHPUnit\Framework\TestCase;

/**
 * Caso base: cada prueba corre dentro de una transacción que se deshace al
 * terminar, así todas parten de los datos de seeds.sql. Sesión vacía en memoria.
 * get() y post() pasan por el router y los middleware como una petición real.
 */
abstract class CasoPrueba extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Reloj::fijar(null);
        Sesion::enMemoria();
        DB::conexion()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $pdo = DB::conexion();
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        Reloj::fijar(null);
        parent::tearDown();
    }

    protected function get(string $ruta, array $query = [], string $ip = '10.0.0.1'): Respuesta
    {
        return App::crear()->manejar(new Peticion('GET', $ruta, $query, [], [], $ip));
    }

    /** POST con el token CSRF de la sesión (salvo que la prueba mande otro). */
    protected function post(string $ruta, array $datos = [], string $ip = '10.0.0.1'): Respuesta
    {
        $datos += ['_csrf' => Csrf::token()];
        return App::crear()->manejar(new Peticion('POST', $ruta, [], $datos, [], $ip));
    }

    /** Inicia sesión directamente, sin pasar por el formulario. */
    protected function entrarComo(string $usuario, string $modo = 'clave'): array
    {
        $u = \App\Models\Usuario::buscarPorUsuario($usuario);
        \App\Core\Auth::iniciar($u, $modo);
        return $u;
    }

    protected function assertRedirige(string $destino, Respuesta $r): void
    {
        $this->assertContains($r->estado, [301, 302, 303], 'Se esperaba una redirección');
        $this->assertSame($destino, $r->cabeceras['Location'] ?? null);
    }
}
