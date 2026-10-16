<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\ErrorHttp;
use App\Core\Peticion;
use App\Core\Respuesta;
use App\Models\Bitacora;
use App\Models\Categoria;

/** Categorías (HU-13). Ver: administrador y cajero; crear y editar: administrador. */
final class CategoriaController extends Controller
{
    public function index(Peticion $peticion): Respuesta
    {
        return $this->pantalla();
    }

    public function editar(Peticion $peticion): Respuesta
    {
        $c = Categoria::buscar((int) $peticion->parametro('id')) ?? throw ErrorHttp::noEncontrado('Esa categoría no existe.');
        return $this->pantalla(['editando' => $c, 'viejo' => ['nombre' => $c['nombre'], 'orden' => (string) $c['orden'], 'color' => $c['color']]]);
    }

    public function crear(Peticion $peticion): Respuesta
    {
        [$datos, $errores] = $this->validar($peticion, 0);
        if ($errores) {
            return $this->pantalla(['errores' => $errores, 'viejo' => $datos], 422);
        }
        $id = Categoria::crear($datos['nombre'], (int) $datos['orden'], $datos['color']);
        Bitacora::registrar('categoria.alta', 'categorias', $id, $datos);
        return $this->redirigirConAviso('/catalogo/categorias', "Categoría {$datos['nombre']} agregada.");
    }

    public function actualizar(Peticion $peticion): Respuesta
    {
        $c = Categoria::buscar((int) $peticion->parametro('id')) ?? throw ErrorHttp::noEncontrado('Esa categoría no existe.');
        [$datos, $errores] = $this->validar($peticion, (int) $c['id']);
        if ($errores) {
            return $this->pantalla(['errores' => $errores, 'viejo' => $datos, 'editando' => $c], 422);
        }
        Categoria::actualizar((int) $c['id'], $datos['nombre'], (int) $datos['orden'], $datos['color']);
        Bitacora::registrar('categoria.edicion', 'categorias', (int) $c['id'], [
            'antes' => ['nombre' => $c['nombre'], 'orden' => (int) $c['orden'], 'color' => $c['color']],
            'despues' => $datos,
        ]);
        return $this->redirigirConAviso('/catalogo/categorias', "Categoría {$datos['nombre']} guardada.");
    }

    public function estado(Peticion $peticion): Respuesta
    {
        $c = Categoria::buscar((int) $peticion->parametro('id')) ?? throw ErrorHttp::noEncontrado('Esa categoría no existe.');
        $activa = $peticion->entrada('activo') === '1';
        Categoria::cambiarEstado((int) $c['id'], $activa);
        Bitacora::registrar($activa ? 'categoria.activar' : 'categoria.desactivar', 'categorias', (int) $c['id']);
        return $this->redirigirConAviso('/catalogo/categorias', "Categoría {$c['nombre']} " . ($activa ? 'activada.' : 'desactivada.'));
    }

    /** @return array{0: array, 1: array} datos limpios y errores por campo */
    private function validar(Peticion $peticion, int $id): array
    {
        $datos = [
            'nombre' => preg_replace('/\s+/u', ' ', (string) $peticion->entrada('nombre')),
            'orden' => (string) $peticion->entrada('orden'),
            'color' => mb_strtolower((string) $peticion->entrada('color')),
        ];
        $errores = [];
        if ($datos['nombre'] === '') {
            $errores['cat-nombre'] = 'Escribe el nombre de la categoría.';
        } elseif (mb_strlen($datos['nombre']) > 24) {
            $errores['cat-nombre'] = 'Usa hasta 24 caracteres.';
        } elseif (Categoria::nombreUsado($datos['nombre'], $id)) {
            $errores['cat-nombre'] = "Ya existe la categoría «{$datos['nombre']}».";
        }
        if (!preg_match('/^[1-9]\d?$/', $datos['orden'])) {
            $errores['cat-orden'] = 'El orden es un número de 1 a 99.';
        }
        if (!isset(Config::get('paleta')[$datos['color']])) {
            $errores['colores'] = 'Elige un color de la paleta.';
        }
        return [$datos, $errores];
    }

    private function pantalla(array $datos = [], int $estado = 200): Respuesta
    {
        return $this->vista('catalogo/categorias', $datos + [
            'titulo' => 'Categorías',
            'modulo' => 'catalogo',
            'h1' => 'Catálogo',
            'css' => ['modulos.css'],
            'js' => ['categorias.js'],
            'categorias' => Categoria::todas(),
            'paleta' => Config::get('paleta'),
            'errores' => [],
            'editando' => null,
            'viejo' => ['nombre' => '', 'orden' => (string) Categoria::siguienteOrden(), 'color' => '#13603e'],
        ], $estado);
    }
}
