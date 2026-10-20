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
use App\Services\FotoProducto;
use App\Services\Imagenes;
use DomainException;
use Throwable;

/**
 * Productos (HU-14). Administrador y cajero ven el catálogo; solo el
 * administrador crea y edita. Agotado (cajero también) y favorito son de un toque.
 */
final class ProductoController extends Controller
{
    private ?string $fotoPreparada = null;
    public function index(Peticion $peticion): Respuesta
    {
        return $this->vista('catalogo/productos', [
            'titulo' => 'Catálogo',
            'modulo' => 'catalogo',
            'h1' => 'Catálogo',
            'css' => ['modulos.css', 'foto-producto.css'],
            'js' => ['productos.js'],
            'productos' => Producto::listar(),
            'categorias' => Categoria::todas(),
            'estado' => $peticion->consulta('estado'),
        ]);
    }

    public function nuevo(Peticion $peticion): Respuesta
    {
        return $this->formulario(null, [
            'codigo' => '', 'nombre' => '', 'nombre_corto' => '', 'descripcion' => '', 'categoria_id' => '',
            'precio' => '', 'unidad' => 'plato', 'activo' => 1, 'favorito' => 0, 'va_a_cocina' => 1, 'agotado' => 0,
        ]);
    }

    public function editar(Peticion $peticion): Respuesta
    {
        $p = $this->producto($peticion);
        return $this->formulario($p, ['precio' => bs($p['precio'])] + $p);
    }

    public function crear(Peticion $peticion): Respuesta
    {
        $codigo = strtoupper((string) $peticion->entrada('codigo'));
        [$d, $errores] = $this->validar($peticion, null);
        if ($codigo === '') {
            $errores['codigo'] = 'Escribe el código.';
        } elseif (!preg_match('/^[A-Z0-9-]{2,12}$/', $codigo)) {
            $errores['codigo'] = 'Usa de 2 a 12 letras, números o guion, sin espacios.';
        } elseif (Producto::codigoUsado($codigo)) {
            $errores['codigo'] = 'Ese código ya lo usa otro producto.';
        }
        if ($errores) {
            return $this->formulario(null, ['codigo' => $codigo] + $d, $errores);
        }

        try {
            $id = $this->guardarConFoto(null, $codigo, $d, $peticion);
        } catch (DomainException $e) {
            return $this->formulario(null, ['codigo' => $codigo] + $d, ['foto' => $e->getMessage()]);
        }
        Bitacora::registrar('producto.alta', 'productos', $id, ['codigo' => $codigo, 'nombre' => $d['nombre'], 'precio' => $d['precio']]);
        return $this->guardado($d);
    }

    public function actualizar(Peticion $peticion): Respuesta
    {
        $p = $this->producto($peticion);
        [$d, $errores] = $this->validar($peticion, $p);
        if ($errores) {
            return $this->formulario($p, ['codigo' => $p['codigo']] + $d, $errores);
        }

        try {
            $this->guardarConFoto($p, $p['codigo'], $d, $peticion);
        } catch (DomainException $e) {
            return $this->formulario($p, ['codigo' => $p['codigo']] + $d, ['foto' => $e->getMessage()]);
        }
        if (bs($p['precio']) !== bs($d['precio'])) {
            Bitacora::registrar('producto.precio', 'productos', (int) $p['id'], [
                'codigo' => $p['codigo'],
                'antes' => $p['precio'],
                'despues' => number_format($d['precio'], 2, '.', ''),
            ]);
        }
        Bitacora::registrar('producto.edicion', 'productos', (int) $p['id'], ['codigo' => $p['codigo']]);
        return $this->guardado($d);
    }

    /** La ruta entrega la foto por id, nunca una ruta enviada por el cliente. */
    public function foto(Peticion $peticion): Respuesta
    {
        $p = $this->producto($peticion);
        $ruta = !empty($p['foto']) ? Imagenes::ruta($p['foto']) : null;
        if ($ruta === null) throw ErrorHttp::noEncontrado('Este producto no tiene foto.');
        return new Respuesta((string) file_get_contents($ruta), 200, [
            'Content-Type' => Imagenes::tipo($p['foto']),
            'Cache-Control' => 'private, no-cache', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function guardarConFoto(?array $actual, string $codigo, array $d, Peticion $peticion): int
    {
        $nuevo = $this->fotoPreparada !== null ? FotoProducto::guardar($this->fotoPreparada) : null;
        $anterior = null;
        $cambia = $nuevo !== null || $peticion->marcado('foto_quitar');
        try {
            $id = DB::transaccion(function () use ($actual, $codigo, $d, $cambia, $nuevo, &$anterior): int {
                if ($actual) {
                    $id = (int) $actual['id'];
                    $p = DB::fila('SELECT foto FROM productos WHERE id = ? FOR UPDATE', [$id]);
                    $anterior = $p['foto'] ?? null;
                    Producto::actualizar($id, $d);
                } else { $id = Producto::crear($codigo, $d); }
                if ($cambia) {
                    Producto::guardarFoto($id, $nuevo);
                    Bitacora::registrar('producto.foto', 'productos', $id, ['antes' => $anterior, 'despues' => $nuevo]);
                }
                return $id;
            });
        } catch (Throwable $e) {
            Imagenes::borrar($nuevo);
            throw $e;
        }
        if ($cambia) Imagenes::borrar($anterior);
        return $id;
    }

    /** Agotado con un toque: se ve en el POS pero no se vende. No desactiva el producto. */
    public function agotado(Peticion $peticion): Respuesta
    {
        return $this->marcar($peticion, 'agotado', 'marcado como agotado.', 'vuelve a estar disponible.');
    }

    public function favorito(Peticion $peticion): Respuesta
    {
        return $this->marcar($peticion, 'favorito', 'ahora es favorito.', 'ya no es favorito.');
    }

    private function marcar(Peticion $peticion, string $campo, string $si, string $no): Respuesta
    {
        $p = $this->producto($peticion);
        $valor = !(int) $p[$campo];
        Producto::marcar((int) $p['id'], $campo, $valor);
        Bitacora::registrar("producto.{$campo}", 'productos', (int) $p['id'], ['codigo' => $p['codigo'], $campo => $valor]);
        $mensaje = $p['nombre'] . ' ' . ($valor ? $si : $no);
        if ($peticion->esperaJson()) {
            return Respuesta::json(['ok' => true, $campo => $valor, 'mensaje' => $mensaje]);
        }
        return $this->redirigirConAviso('/catalogo/productos', $mensaje);
    }

    /** @return array{0: array, 1: array} datos limpios y errores por campo */
    private function validar(Peticion $peticion, ?array $actual): array
    {
        $precio = (string) $peticion->entrada('precio');
        $d = [
            'nombre' => (string) $peticion->entrada('nombre'),
            'nombre_corto' => (string) $peticion->entrada('nombre_corto'),
            'descripcion' => (string) $peticion->entrada('descripcion'),
            'categoria_id' => (int) $peticion->entrada('categoria_id'),
            'precio' => leer_monto($precio),
            'unidad' => (string) $peticion->entrada('unidad'),
            'activo' => $peticion->marcado('activo') ? 1 : 0,
            'favorito' => $peticion->marcado('favorito') ? 1 : 0,
            'va_a_cocina' => $peticion->marcado('va_a_cocina') ? 1 : 0,
            'agotado' => $peticion->marcado('agotado') ? 1 : 0,
        ];

        $errores = [];
        if ($d['nombre'] === '') {
            $errores['nombre'] = 'Escribe el nombre del producto.';
        } elseif (mb_strlen($d['nombre']) > 80) {
            $errores['nombre'] = 'Usa hasta 80 caracteres.';
        }
        if ($d['nombre_corto'] === '') {
            $errores['corto'] = 'Escribe el nombre corto para el botón.';
        } elseif (mb_strlen($d['nombre_corto']) > 16) {
            $errores['corto'] = 'Usa hasta 16 letras: es lo que cabe en el botón.';
        }
        if (mb_strlen($d['descripcion']) > 200) {
            $errores['descripcion'] = 'Usa hasta 200 caracteres.';
        }
        $this->fotoPreparada = null;
        if ($archivo = $peticion->archivo('foto')) {
            try { $this->fotoPreparada = FotoProducto::recibir($archivo); }
            catch (DomainException $e) { $errores['foto'] = $e->getMessage(); }
        }
        // Categoría activa, o la que ya tenía el producto aunque luego se haya desactivado
        $categoria = Categoria::buscar($d['categoria_id']);
        if ($categoria === null || (!(int) $categoria['activo'] && (int) ($actual['categoria_id'] ?? 0) !== $d['categoria_id'])) {
            $errores['categoria'] = 'Elige una categoría activa.';
        }
        if ($precio === '') {
            $errores['precio'] = 'Escribe el precio.';
        } elseif ($d['precio'] === null || $d['precio'] <= 0 || $d['precio'] > 99999.99) {
            $errores['precio'] = 'Escribe un precio mayor a 0, por ejemplo 45,50.';
        }
        if (!isset(Producto::UNIDADES[$d['unidad']])) {
            $errores['unidad'] = 'Elige una unidad de la lista.';
        }
        if ($errores) {
            $d['precio'] = $precio;
        }
        return [$d, $errores];
    }

    private function guardado(array $d): Respuesta
    {
        return $this->redirigirConAviso(
            '/catalogo/productos',
            $d['activo'] ? "{$d['nombre']} guardado. Ya aparece en el punto de venta." : "{$d['nombre']} guardado como inactivo: no aparece en el punto de venta.",
        );
    }

    private function formulario(?array $producto, array $valores, array $errores = []): Respuesta
    {
        $titulo = $producto ? 'Editar producto' : 'Nuevo producto';
        $categorias = array_values(array_filter(
            Categoria::todas(),
            static fn (array $c): bool => (int) $c['activo'] || (int) $c['id'] === (int) ($valores['categoria_id'] ?? 0),
        ));
        return $this->vista('catalogo/producto-form', [
            'titulo' => $producto ? 'Editar ' . $producto['nombre'] : $titulo,
            'modulo' => 'catalogo',
            'h1' => $titulo,
            'css' => ['modulos.css', 'foto-producto.css'],
            'js' => ['producto-form.js', 'foto-producto.js'],
            'producto' => $producto,
            'v' => $valores,
            'errores' => $errores,
            'categorias' => $categorias,
            'precios' => $producto ? DB::filas(
                "SELECT b.creado_en, u.nombre, b.detalle FROM bitacora b LEFT JOIN usuarios u ON u.id = b.usuario_id
                 WHERE b.accion = 'producto.precio' AND b.entidad_id = ? ORDER BY b.id DESC LIMIT 10",
                [(string) $producto['id']],
            ) : [],
        ], $errores ? 422 : 200);
    }

    private function producto(Peticion $peticion): array
    {
        return Producto::buscar((int) $peticion->parametro('id')) ?? throw ErrorHttp::noEncontrado('Ese producto no existe.');
    }
}
