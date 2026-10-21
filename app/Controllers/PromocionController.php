<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;
use App\Core\ErrorHttp;
use App\Core\Peticion;
use App\Core\Respuesta;
use App\Models\Bitacora;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Promocion;
use App\Services\PromocionService;

/**
 * Promociones (HU-46), solo el administrador. Por ahora, descuento por producto o por
 * categoría. Nada se borra: una promoción se pausa y se vuelve a activar.
 */
final class PromocionController extends Controller
{
    public function index(Peticion $peticion): Respuesta
    {
        return $this->vista('promociones/index', [
            'titulo' => 'Promociones',
            'modulo' => 'promociones',
            'h1' => 'Promociones',
            'css' => ['modulos.css'],
            'promociones' => Promocion::todas(),
            'usos' => Promocion::usos(),
        ]);
    }

    public function nueva(Peticion $peticion): Respuesta
    {
        return $this->formulario(null, ['nombre' => '', 'aplica' => '', 'valor' => '', 'valor_tipo' => 'porcentaje', 'activo' => 1]);
    }

    public function editar(Peticion $peticion): Respuesta
    {
        $p = $this->buscar($peticion);
        return $this->formulario($p, [
            'nombre' => $p['nombre'],
            'aplica' => $p['producto_id'] !== null ? 'p:' . $p['producto_id'] : 'c:' . $p['categoria_id'],
            'valor' => bs($p['valor']),
            'valor_tipo' => $p['valor_tipo'],
            'activo' => (int) $p['activo'],
        ]);
    }

    public function crear(Peticion $peticion): Respuesta
    {
        [$v, $datos, $errores] = $this->validar($peticion);
        if ($errores) {
            return $this->formulario(null, $v, $errores, 422);
        }
        $id = Promocion::crear($datos);
        Bitacora::registrar('promocion.alta', 'promociones', $id, $this->detalle($datos));
        return $this->redirigirConAviso('/promociones', "Promoción «{$datos['nombre']}» creada." . ($datos['activo'] ? ' Ya se aplica en el punto de venta.' : ''));
    }

    public function actualizar(Peticion $peticion): Respuesta
    {
        $p = $this->buscar($peticion);
        [$v, $datos, $errores] = $this->validar($peticion);
        if ($errores) {
            return $this->formulario($p, $v, $errores, 422);
        }
        Promocion::actualizar((int) $p['id'], $datos);
        Bitacora::registrar('promocion.edicion', 'promociones', (int) $p['id'], [
            'antes' => $this->detalle($p),
            'despues' => $this->detalle($datos),
        ]);
        return $this->redirigirConAviso('/promociones', "Promoción «{$datos['nombre']}» guardada.");
    }

    public function estado(Peticion $peticion): Respuesta
    {
        $p = $this->buscar($peticion);
        $activa = $peticion->entrada('activo') === '1';
        Promocion::cambiarEstado((int) $p['id'], $activa);
        Bitacora::registrar($activa ? 'promocion.activar' : 'promocion.desactivar', 'promociones', (int) $p['id'], ['nombre' => $p['nombre']]);
        return $this->redirigirConAviso('/promociones', "Promoción «{$p['nombre']}» " . ($activa ? 'activada.' : 'pausada.'));
    }

    private function buscar(Peticion $peticion): array
    {
        return Promocion::buscar((int) $peticion->parametro('id')) ?? throw ErrorHttp::noEncontrado('Esa promoción no existe.');
    }

    /** @return array{0: array, 1: array, 2: array} valores del formulario, datos limpios y errores por campo */
    private function validar(Peticion $peticion): array
    {
        $v = [
            'nombre' => trim((string) preg_replace('/\s+/u', ' ', (string) $peticion->entrada('nombre'))),
            'aplica' => (string) $peticion->entrada('aplica'),
            'valor' => trim((string) $peticion->entrada('valor')),
            'valor_tipo' => (string) $peticion->entrada('valor_tipo'),
            'activo' => $peticion->entrada('activo') === '1' ? 1 : 0,
        ];
        $errores = [];
        $datos = ['nombre' => $v['nombre'], 'valor_tipo' => $v['valor_tipo'], 'valor' => '', 'producto_id' => null, 'categoria_id' => null, 'activo' => $v['activo']];

        if ($v['nombre'] === '') {
            $errores['nombre'] = 'Escribe el nombre: así se verá en el pedido y en el ticket.';
        } elseif (mb_strlen($v['nombre']) > 40) {
            $errores['nombre'] = 'Usa hasta 40 caracteres, para que quepa en el ticket.';
        }

        // A qué se aplica: un producto (p:ID) o una categoría (c:ID)
        $producto = $categoria = null;
        if (preg_match('/^p:(\d+)$/D', $v['aplica'], $m)) {
            $producto = Producto::buscar((int) $m[1]);
        } elseif (preg_match('/^c:(\d+)$/D', $v['aplica'], $m)) {
            $categoria = Categoria::buscar((int) $m[1]);
        }
        if ($producto === null && $categoria === null) {
            $errores['aplica'] = 'Elige el producto o la categoría que tiene el descuento.';
        }
        $datos['producto_id'] = $producto ? (int) $producto['id'] : null;
        $datos['categoria_id'] = $categoria ? (int) $categoria['id'] : null;

        if (!in_array($v['valor_tipo'], ['porcentaje', 'monto'], true)) {
            $errores['valor'] = 'Elige si el descuento es en % o en Bs.';
        } else {
            $valor = leer_monto($v['valor']);
            if ($valor === null || $valor <= 0) {
                $errores['valor'] = 'Escribe el descuento, mayor que cero y con hasta dos decimales.';
            } elseif ($v['valor_tipo'] === 'porcentaje' && $valor >= 100) {
                $errores['valor'] = 'El porcentaje debe ser menor que 100.';
            } elseif ($v['valor_tipo'] === 'monto' && ($tope = $this->precioMinimo($producto, $categoria)) !== null && $valor >= $tope['precio']) {
                $errores['valor'] = "Debe ser menor que el precio de {$tope['nombre']} (Bs " . bs($tope['precio']) . '), para que no quede gratis.';
            } else {
                $datos['valor'] = number_format($valor, 2, '.', '');
            }
        }
        return [$v, $datos, $errores];
    }

    /** El precio más bajo que tocaría un descuento en Bs: el del producto, o el más barato activo de la categoría. */
    private function precioMinimo(?array $producto, ?array $categoria): ?array
    {
        if ($producto !== null) {
            return ['nombre' => $producto['nombre'], 'precio' => (float) $producto['precio']];
        }
        if ($categoria === null) {
            return null;
        }
        $f = DB::fila('SELECT nombre, precio FROM productos WHERE categoria_id = ? AND activo = 1 ORDER BY precio, nombre LIMIT 1', [(int) $categoria['id']]);
        return $f ? ['nombre' => $f['nombre'], 'precio' => (float) $f['precio']] : null;
    }

    /** Detalle legible para la bitácora. */
    private function detalle(array $d): array
    {
        $aplica = $d['producto_id'] !== null
            ? 'Producto ' . (Producto::buscar((int) $d['producto_id'])['nombre'] ?? '')
            : 'Categoría ' . (Categoria::buscar((int) $d['categoria_id'])['nombre'] ?? '');
        return ['nombre' => $d['nombre'], 'aplica' => $aplica, 'descuento' => PromocionService::regla($d), 'activo' => (bool) (int) $d['activo']];
    }

    private function formulario(?array $promocion, array $v, array $errores = [], int $estado = 200): Respuesta
    {
        return $this->vista('promociones/form', [
            'titulo' => $promocion ? 'Editar promoción' : 'Nueva promoción',
            'modulo' => 'promociones',
            'h1' => $promocion ? 'Editar promoción' : 'Nueva promoción',
            'css' => ['modulos.css'],
            'promocion' => $promocion,
            'v' => $v,
            'errores' => $errores,
            'productos' => DB::filas('SELECT p.id, p.nombre, p.precio, c.nombre AS categoria FROM productos p JOIN categorias c ON c.id = p.categoria_id WHERE p.activo = 1 ORDER BY c.orden, p.nombre'),
            'categorias' => Categoria::activas(),
        ], $estado);
    }
}
