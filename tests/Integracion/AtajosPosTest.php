<?php

declare(strict_types=1);

namespace Tests\Integracion;

use App\Core\DB;
use App\Core\Reloj;
use Tests\CasoPrueba;

/**
 * HU-33: atajos de teclado del punto de venta. Las teclas las atiende pos.js; aquí
 * se prueba que cada acción declara su atajo y lo muestra en pantalla. El recorrido
 * con el teclado está en la guía de QA del README.
 */
final class AtajosPosTest extends CasoPrueba
{
    private string $html;

    protected function setUp(): void
    {
        parent::setUp();
        Reloj::fijar('2026-10-19 12:00:00');
        $this->entrarComo('mariela', 'pin');
        $this->post('/caja/abrir', ['turno_id' => (int) DB::valor("SELECT id FROM turnos WHERE clave = 'mediodia'"), 'fondo' => '300']);
        $this->html = $this->get('/pos')->cuerpo;
    }

    /**
     * Escenario: Atajos visibles
     *   Dado estoy en el punto de venta con un teclado
     *   Cuando miro la pantalla
     *   Entonces cada acción muestra su tecla: F2 buscar, F4 cobrar, F8 efectivo exacto y Esc cancelar
     */
    public function testCadaAccionMuestraSuTecla(): void
    {
        $this->assertMatchesRegularExpression('/id="pos-buscar"[^>]*aria-keyshortcuts="F2"/', $this->html);
        $this->assertStringContainsString('<kbd class="atajo control-grupo__atajo" aria-hidden="true">F2</kbd>', $this->html);
        $this->assertMatchesRegularExpression('/id="cobrar" aria-keyshortcuts="F4"[^>]*>\s*<span id="cobrar-texto">Cobrar<\/span> <kbd class="atajo">F4<\/kbd>/', $this->html);
        $this->assertMatchesRegularExpression('/id="efectivo-exacto" aria-keyshortcuts="F8"[^>]*>.*?Efectivo exacto <kbd class="atajo atajo--claro">F8<\/kbd>/s', $this->html);
        $this->assertMatchesRegularExpression('/id="vaciar"[^>]*aria-keyshortcuts="Escape"[^>]*>.*?Vaciar <kbd class="atajo">Esc<\/kbd>/s', $this->html);
    }

    /**
     * Escenario: Cancelar con Esc pide confirmación
     *   Dado tengo un pedido cargado
     *   Cuando presiono Esc
     *   Entonces se abre la confirmación y «Seguir vendiendo» queda enfocado, para que un Enter no borre el pedido
     */
    public function testEscCancelaConConfirmacion(): void
    {
        $this->assertMatchesRegularExpression('/<dialog class="modal" id="modal-vaciar".*?<button class="boton" type="submit" value="seguir" autofocus>Seguir vendiendo<\/button>/s', $this->html);
    }

    public function testElScriptAtiendeLasCuatroTeclas(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/pos.js');

        $this->assertStringContainsString("['F2', 'F4', 'F8', 'Escape']", $js);
    }
}
