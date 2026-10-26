<?php

declare(strict_types=1);

namespace Tests\Integracion;

use App\Models\Bitacora;
use Tests\CasoPrueba;

/** HU-04: control de acceso por rol en el servidor y menú lateral filtrado. */
final class PermisosTest extends CasoPrueba
{
    /**
     * Escenario: Permiso validado en el servidor
     *   Dado un usuario con rol Cajero
     *   Cuando abre la URL de Usuarios escribiéndola a mano
     *   Entonces recibe un mensaje de acceso denegado y no ve datos
     */
    public function testCajeroQueEscribeLaUrlDeUsuariosRecibeAccesoDenegado(): void
    {
        $this->entrarComo('mariela', 'pin');

        $r = $this->get('/usuarios');

        $this->assertSame(403, $r->estado);
        $this->assertStringContainsString('No tienes permiso para entrar a Usuarios', $r->cuerpo);
        $this->assertStringNotContainsString('Ana Lucía', $r->cuerpo, 'No debe mostrar datos del módulo');
        $denegado = Bitacora::ultimos('acceso.denegado');
        $this->assertCount(1, $denegado);
        $this->assertStringContainsString('/usuarios', $denegado[0]['detalle']);
    }

    public function testMenuDelCajero(): void
    {
        $this->entrarComo('mariela', 'pin');

        $menu = $this->modulosDelMenu($this->get('/caja')->cuerpo);

        // HU-41: Conciliación QR es del administrador (confirma o rechaza); el cajero recibe
        // los avisos de pagos rechazados en su pantalla
        $this->assertSame(['tablero', 'pos', 'caja', 'ventas', 'qr', 'clientes', 'catalogo'], $menu);
    }

    public function testMenuDelAdministradorTieneTodo(): void
    {
        $this->entrarComo('admin');

        $menu = $this->modulosDelMenu($this->get('/tablero')->cuerpo);

        $this->assertCount(14, $menu);
        $this->assertContains('usuarios', $menu);
        $this->assertContains('config', $menu);
    }

    public function testMenuDelMeseroSoloPedidos(): void
    {
        $this->entrarComo('luis');

        $this->assertSame(['mesero'], $this->modulosDelMenu($this->get('/mesero')->cuerpo));
        $this->assertSame(403, $this->get('/caja')->estado);
        $this->assertSame(403, $this->get('/pos')->estado);
    }

    public function testCadaRolSoloEntraASusModulos(): void
    {
        $matriz = [
            'admin' => ['/tablero' => 200, '/usuarios' => 200, '/config' => 200, '/reportes' => 200, '/mesero' => 200],
            'mariela' => ['/tablero' => 200, '/caja' => 200, '/promociones' => 403, '/reportes' => 403, '/config' => 403, '/bitacora' => 403, '/mesero' => 403],
            'luis' => ['/mesero' => 200, '/tablero' => 403, '/catalogo/productos' => 403, '/usuarios' => 403],
        ];
        foreach ($matriz as $usuario => $rutas) {
            $this->entrarComo($usuario);
            foreach ($rutas as $ruta => $estado) {
                $this->assertSame($estado, $this->get($ruta)->estado, "{$usuario} en {$ruta}");
            }
        }
    }

    public function testElMenuSeparaSoloGruposConModulos(): void
    {
        $this->entrarComo('luis');

        $this->assertStringNotContainsString('menu__separador', $this->get('/mesero')->cuerpo);
    }

    public function testElBodyLlevaLosPermisosDelRolParaElJs(): void
    {
        $this->entrarComo('mariela', 'pin');

        $cuerpo = $this->get('/caja')->cuerpo;

        $this->assertMatchesRegularExpression('/data-permisos="[^"]*catalogo\.agotado/', $cuerpo);
        $this->assertDoesNotMatchRegularExpression('/data-permisos="[^"]*catalogo\.editar/', $cuerpo);
    }

    private function modulosDelMenu(string $html): array
    {
        preg_match_all('/class="menu__item" data-modulo="([a-z]+)"/', $html, $m);
        return $m[1];
    }
}
