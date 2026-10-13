<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function testResuelveRutaConParametroNumerico(): void
    {
        $r = new Router();
        $r->get('/catalogo/productos/{id}/editar', ['C', 'editar'], ['auth']);

        [$estado, $accion, $mw, $params] = $r->resolver('GET', '/catalogo/productos/12/editar');

        $this->assertSame(200, $estado);
        $this->assertSame(['C', 'editar'], $accion);
        $this->assertSame(['auth'], $mw);
        $this->assertSame(['id' => '12'], $params);
    }

    public function testIdNoNumericoNoCoincide(): void
    {
        $r = new Router();
        $r->get('/catalogo/productos/{id}/editar', ['C', 'editar']);

        $this->assertSame(404, $r->resolver('GET', '/catalogo/productos/abc/editar')[0]);
    }

    public function testMetodoEquivocadoDa405(): void
    {
        $r = new Router();
        $r->post('/login', ['C', 'entrar']);

        $this->assertSame(405, $r->resolver('GET', '/login')[0]);
    }

    public function testRaiz(): void
    {
        $r = new Router();
        $r->get('/', ['C', 'index']);

        $this->assertSame(200, $r->resolver('GET', '/')[0]);
    }
}
