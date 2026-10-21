<?php

declare(strict_types=1);

namespace Tests\Integracion;

use App\Core\DB;
use App\Core\Respuesta;
use App\Models\Bitacora;
use Tests\CasoPrueba;

/** HU-46: el administrador crea, edita, pausa y activa descuentos por producto o categoría. */
final class PromocionesTest extends CasoPrueba
{
    private int $majadito;
    private int $platos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entrarComo('admin');
        $this->majadito = (int) DB::valor("SELECT id FROM productos WHERE codigo = 'PL-01'");
        $this->platos = (int) DB::valor("SELECT id FROM categorias WHERE nombre = 'Platos'");
    }

    private function crear(array $datos): Respuesta
    {
        return $this->post('/promociones/nueva', $datos + ['nombre' => 'Martes de majadito', 'aplica' => 'p:' . $this->majadito, 'valor' => '10', 'valor_tipo' => 'porcentaje', 'activo' => '1']);
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

        $r = $this->post('/promociones/' . $id, ['nombre' => 'Martes de majadito', 'aplica' => 'p:' . $this->majadito, 'valor' => '15', 'valor_tipo' => 'porcentaje', 'activo' => '1']);

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

    public function testSinPromocionesMuestraElEstadoVacio(): void
    {
        $this->assertStringContainsString('Todavía no hay promociones', $this->get('/promociones')->cuerpo);
    }
}
