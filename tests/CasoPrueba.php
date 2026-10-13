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
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Caso base: cada prueba empieza con los datos de seeds.sql y una sesión vacía.
 * get() y post() pasan por el router y los middleware como una petición real.
 */
abstract class CasoPrueba extends TestCase
{
    private const TABLAS_FIJAS = ['migraciones'];

    protected function setUp(): void
    {
        parent::setUp();
        Reloj::fijar(null);
        Sesion::enMemoria();
        $this->restaurarDatos();
    }

    private function restaurarDatos(): void
    {
        $pdo = DB::conexion();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN) as $tabla) {
            if (!in_array($tabla, self::TABLAS_FIJAS, true)) {
                $pdo->exec("TRUNCATE TABLE `{$tabla}`");
            }
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        $migrador = new \App\Core\Migrador($pdo, App::raiz() . '/database');
        $migrador->sembrarSiVacia();
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
