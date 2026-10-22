<?php

declare(strict_types=1);

namespace Tests\Integracion;

use App\Core\DB;
use App\Core\Reloj;
use App\Core\Respuesta;
use App\Models\Bitacora;
use Tests\CasoPrueba;

/**
 * HU-46: el administrador crea, edita, pausa y activa descuentos por producto o categoría.
 * HU-49: restricciones de fechas, días, horario, cantidad, monto y tope por día.
 */
final class PromocionesTest extends CasoPrueba
{
    private int $majadito;
    private int $platos;

    protected function setUp(): void
    {
        parent::setUp();
        Reloj::fijar('2026-10-22 12:30:00');   // jueves
        $this->entrarComo('admin');
        $this->majadito = (int) DB::valor("SELECT id FROM productos WHERE codigo = 'PL-01'");
        $this->platos = (int) DB::valor("SELECT id FROM categorias WHERE nombre = 'Platos'");
    }

    private function crear(array $datos): Respuesta
    {
        return $this->post('/promociones/nueva', $datos + ['nombre' => 'Martes de majadito', 'aplica' => 'p:' . $this->majadito, 'valor' => '10', 'valor_tipo' => 'porcentaje', 'activo' => '1',
            'dias' => ['1', '2', '3', '4', '5', '6', '7']]);
    }

    /**
     * Escenario: Crear un descuento por producto
     *   Dado soy administrador
     *   Cuando creo «Martes de majadito» con 10 % al Majadito
     *   Entonces queda activa, aparece en la lista y queda en la bitácora
     */
    public function testCreaUnDescuentoPorProducto(): void
    {
        $this->assertRedirige('/promociones', $this->crear([]));

        $p = DB::fila("SELECT * FROM promociones WHERE nombre = 'Martes de majadito'");
        $this->assertSame(['porcentaje', '10.00', $this->majadito, null, 1], [$p['valor_tipo'], $p['valor'], (int) $p['producto_id'], $p['categoria_id'], (int) $p['activo']]);
        $lista = $this->get('/promociones')->cuerpo;
        $this->assertStringContainsString('Martes de majadito', $lista);
        $this->assertStringContainsString('10 % menos', $lista);
        $registro = Bitacora::ultimos('promocion.alta');
        $this->assertCount(1, $registro);
        $this->assertStringContainsString('"aplica":"Producto Majadito de charque"', (string) $registro[0]['detalle']);
    }

    public function testCreaUnDescuentoPorCategoriaEnBs(): void
    {
        $this->assertRedirige('/promociones', $this->crear(['nombre' => 'Platos del día', 'aplica' => 'c:' . $this->platos, 'valor' => '5,50', 'valor_tipo' => 'monto']));

        $p = DB::fila("SELECT * FROM promociones WHERE nombre = 'Platos del día'");
        $this->assertSame(['monto', '5.50', null, $this->platos], [$p['valor_tipo'], $p['valor'], $p['producto_id'], (int) $p['categoria_id']]);
        $this->assertStringContainsString('Bs 5,50 menos por unidad', $this->get('/promociones')->cuerpo);
    }

    public static function invalidos(): array
    {
        return [
            'sin nombre' => [['nombre' => '  '], 'nombre', 'Escribe el nombre: así se verá en el pedido y en el ticket.'],
            'nombre largo' => [['nombre' => str_repeat('a', 41)], 'nombre', 'Usa hasta 40 caracteres, para que quepa en el ticket.'],
            'sin objetivo' => [['aplica' => ''], 'aplica', 'Elige el producto o la categoría que tiene el descuento.'],
            'producto inexistente' => [['aplica' => 'p:99999'], 'aplica', 'Elige el producto o la categoría que tiene el descuento.'],
            'cero' => [['valor' => '0'], 'valor', 'Escribe el descuento, mayor que cero y con hasta dos decimales.'],
            'letras' => [['valor' => 'diez'], 'valor', 'Escribe el descuento, mayor que cero y con hasta dos decimales.'],
            '100 %' => [['valor' => '100'], 'valor', 'El porcentaje debe ser menor que 100.'],
            'tipo raro' => [['valor_tipo' => 'puntos'], 'valor', 'Elige si el descuento es en % o en Bs.'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidos')]
    public function testValidaElFormulario(array $datos, string $campo, string $mensaje): void
    {
        $r = $this->crear($datos);

        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString(e($mensaje), $r->cuerpo);
        $this->assertStringContainsString('id="' . $campo . '"', $r->cuerpo);
        $this->assertSame(0, (int) DB::valor('SELECT COUNT(*) FROM promociones'));
    }

    /**
     * Escenario: Un descuento en Bs no deja un producto gratis
     *   Dado el Majadito cuesta Bs 40 y el producto más barato de Platos, Bs 15
     *   Cuando creo un descuento de Bs 40 al Majadito, o de Bs 15 a Platos
     *   Entonces se rechaza y explica el precio que limita
     */
    public function testElMontoEsMenorQueElPrecio(): void
    {
        $producto = $this->crear(['valor' => '40', 'valor_tipo' => 'monto']);
        $categoria = $this->crear(['aplica' => 'c:' . $this->platos, 'valor' => '15', 'valor_tipo' => 'monto']);

        $this->assertStringContainsString('Debe ser menor que el precio de Majadito de charque (Bs 40,00), para que no quede gratis.', $producto->cuerpo);
        $this->assertStringContainsString('Debe ser menor que el precio de Sonso de yuca (Bs 15,00), para que no quede gratis.', $categoria->cuerpo);
    }

    public function testEditaYRegistraElCambio(): void
    {
        $this->crear([]);
        $id = (int) DB::valor('SELECT id FROM promociones');

        $form = $this->get('/promociones/' . $id . '/editar')->cuerpo;
        $this->assertStringContainsString('value="Martes de majadito"', $form);
        $this->assertStringContainsString('value="p:' . $this->majadito . '" selected', $form);

        $r = $this->post('/promociones/' . $id, ['nombre' => 'Martes de majadito', 'aplica' => 'p:' . $this->majadito, 'valor' => '15', 'valor_tipo' => 'porcentaje', 'activo' => '1', 'dias' => ['2']]);

        $this->assertRedirige('/promociones', $r);
        $this->assertSame('15.00', DB::valor('SELECT valor FROM promociones WHERE id = ?', [$id]));
        $detalle = json_decode((string) Bitacora::ultimos('promocion.edicion')[0]['detalle'], true);
        $this->assertSame(['10 % menos', '15 % menos'], [$detalle['antes']['descuento'], $detalle['despues']['descuento']]);
    }

    /**
     * Escenario: Pausar sin borrar
     *   Dado una promoción activa
     *   Cuando la pauso
     *   Entonces deja de aplicarse, sigue en la lista y se puede volver a activar
     */
    public function testPausaYActivaSinBorrar(): void
    {
        $this->crear([]);
        $id = (int) DB::valor('SELECT id FROM promociones');

        $this->assertRedirige('/promociones', $this->post('/promociones/' . $id . '/estado', ['activo' => '0']));
        $this->assertSame(0, (int) DB::valor('SELECT activo FROM promociones WHERE id = ?', [$id]));
        $this->assertStringContainsString('Pausada', $this->get('/promociones')->cuerpo);
        $this->assertCount(1, Bitacora::ultimos('promocion.desactivar'));

        $this->post('/promociones/' . $id . '/estado', ['activo' => '1']);
        $this->assertSame(1, (int) DB::valor('SELECT activo FROM promociones WHERE id = ?', [$id]));
        $this->assertCount(1, Bitacora::ultimos('promocion.activar'));
    }

    public function testSoloElAdministrador(): void
    {
        $this->entrarComo('mariela', 'pin');

        $this->assertSame(403, $this->get('/promociones')->estado);
        $this->assertSame(403, $this->crear([])->estado);
        $this->assertSame(0, (int) DB::valor('SELECT COUNT(*) FROM promociones'));
    }

    /**
     * Escenario: Restricciones combinables
     *   Dado soy administrador
     *   Cuando creo una promoción de lunes a viernes, de 12:00 a medianoche, en noviembre,
     *        desde 2 unidades, con pedidos desde Bs 50 y tope de 20 ventas por día
     *   Entonces se guardan todas y la lista las muestra en palabras
     */
    public function testGuardaLasRestriccionesYLasMuestra(): void
    {
        $r = $this->crear(['fecha_desde' => '2026-11-01', 'fecha_hasta' => '2026-11-30', 'dias' => ['1', '2', '3', '4', '5'],
            'hora_desde' => '12:00', 'hora_hasta' => '23:59', 'cantidad_minima' => '2', 'monto_minimo' => '50', 'tope_usos_dia' => '20']);

        $this->assertRedirige('/promociones', $r);
        $p = DB::fila('SELECT fecha_desde, fecha_hasta, dias, hora_desde, hora_hasta, cantidad_minima, monto_minimo, tope_usos_dia FROM promociones');
        $this->assertSame(['2026-11-01', '2026-11-30', 31, '12:00:00', '24:00:00', 2, '50.00', 20],
            [$p['fecha_desde'], $p['fecha_hasta'], (int) $p['dias'], $p['hora_desde'], $p['hora_hasta'], (int) $p['cantidad_minima'], $p['monto_minimo'], (int) $p['tope_usos_dia']]);

        $lista = $this->get('/promociones')->cuerpo;
        $this->assertStringContainsString('De lunes a viernes, 12:00 a 24:00 · del 01/11/2026 al 30/11/2026', $lista);
        $this->assertStringContainsString('Desde 2 unidades · pedido desde Bs 50,00 · tope 20 por día', $lista);
        $this->assertStringContainsString('Programada', $lista);
        $this->assertStringContainsString('"cuando":"De lunes a viernes, 12:00 a 24:00', (string) Bitacora::ultimos('promocion.alta')[0]['detalle']);
    }

    public function testElFormularioDeEdicionRecuperaLasRestricciones(): void
    {
        $this->crear(['dias' => ['2', '4'], 'hora_desde' => '18:00', 'hora_hasta' => '23:59', 'monto_minimo' => '75,5']);
        $id = (int) DB::valor('SELECT id FROM promociones');

        $form = $this->get('/promociones/' . $id . '/editar')->cuerpo;

        $this->assertStringContainsString('name="dias[]" value="2" checked', $form);
        $this->assertStringContainsString('name="dias[]" value="4" checked', $form);
        $this->assertStringContainsString('name="dias[]" value="1">', $form);
        $this->assertStringContainsString('id="hora_hasta" name="hora_hasta" type="time" value="23:59"', $form);
        $this->assertStringContainsString('value="75,50"', $form);
    }

    public static function restriccionesInvalidas(): array
    {
        return [
            'fecha imposible' => [['fecha_desde' => '2026-02-30'], 'fecha_desde', 'Escribe una fecha válida, o déjala vacía.'],
            'fechas al revés' => [['fecha_desde' => '2026-11-30', 'fecha_hasta' => '2026-11-01'], 'fecha_hasta', 'La fecha final no puede ser anterior a la inicial.'],
            'sin días' => [['dias' => []], 'dias', 'Elige al menos un día.'],
            'una sola hora' => [['hora_desde' => '12:00'], 'hora_hasta', 'Escribe las dos horas de la franja, o deja las dos vacías.'],
            'horas al revés' => [['hora_desde' => '15:00', 'hora_hasta' => '12:00'], 'hora_hasta', 'La hora final debe ser después de la inicial.'],
            'cantidad 1' => [['cantidad_minima' => '1'], 'cantidad_minima', 'De 2 a 999 unidades. Déjala vacía si basta con una.'],
            'monto cero' => [['monto_minimo' => '0'], 'monto_minimo', 'Escribe un monto mayor que cero, o déjalo vacío.'],
            'tope cero' => [['tope_usos_dia' => '0'], 'tope_usos_dia', 'De 1 a 9999 ventas por día, o déjalo vacío.'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('restriccionesInvalidas')]
    public function testValidaLasRestricciones(array $datos, string $campo, string $mensaje): void
    {
        $r = $this->crear($datos);

        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString(e($mensaje), $r->cuerpo);
        $this->assertStringContainsString('id="' . $campo . '"', $r->cuerpo);
        $this->assertSame(0, (int) DB::valor('SELECT COUNT(*) FROM promociones'));
    }

    /**
     * Escenario: Estado en este momento
     *   Dado es jueves a las 12:30
     *   Cuando miro la lista
     *   Entonces cada promoción dice si aplica ahora o por qué no
     */
    public function testLaListaDiceSiAplicaAhora(): void
    {
        $todos = ['1', '2', '3', '4', '5', '6', '7'];
        $this->crear(['nombre' => 'Siempre']);
        $this->crear(['nombre' => 'Fin de semana', 'dias' => ['6', '7']]);
        $this->crear(['nombre' => 'Noche', 'hora_desde' => '18:00', 'hora_hasta' => '23:59', 'dias' => $todos]);
        $this->crear(['nombre' => 'Septiembre', 'fecha_desde' => '2026-09-01', 'fecha_hasta' => '2026-09-30']);

        $filas = [];
        preg_match_all('/<tr>\s*<td><span class="tabla__principal">([^<]+)<\/span>.*?data-etiqueta="Ahora"><span class="insignia[^"]*">([^<]+)<\/span>/s', $this->get('/promociones')->cuerpo, $m, PREG_SET_ORDER);
        foreach ($m as [, $nombre, $estado]) {
            $filas[$nombre] = $estado;
        }

        ksort($filas);
        $this->assertSame(['Fin de semana' => 'Hoy no aplica', 'Noche' => 'Fuera de horario', 'Septiembre' => 'Vencida', 'Siempre' => 'Aplica ahora'], $filas);
    }

    public function testSinPromocionesMuestraElEstadoVacio(): void
    {
        $this->assertStringContainsString('Todavía no hay promociones', $this->get('/promociones')->cuerpo);
    }
}
