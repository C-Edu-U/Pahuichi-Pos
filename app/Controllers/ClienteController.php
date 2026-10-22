<?php

declare(strict_types=1);
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Peticion;
use App\Core\Respuesta;
use App\Models\Cliente;
use App\Services\ClienteService;
use DomainException;

final class ClienteController extends Controller
{
    public function index(Peticion $peticion): Respuesta
    {
        return $this->pantalla(['q' => mb_substr($peticion->consulta('q'), 0, 120)]);
    }

    public function buscar(Peticion $peticion): Respuesta
    {
        $q = strtoupper($peticion->consulta('q'));
        $filas = preg_match('/^[0-9][A-Z0-9-]{0,25}$/D', $q) ? Cliente::sugerencias($q) : [];
        return Respuesta::json(['clientes' => $filas])->cabecera('Cache-Control', 'private, no-store');
    }

    public function crear(Peticion $peticion): Respuesta
    {
        try {
            $cliente = ClienteService::guardar($peticion->entrada('doc'), $peticion->entrada('nombre'), $peticion->entrada('tipo', 'NIT'));
            if ($peticion->esperaJson()) return Respuesta::json(['cliente' => $cliente], 201);
            return $this->redirigirConAviso('/clientes', 'Cliente guardado: ' . $cliente['nombre'] . '.');
        } catch (DomainException $e) {
            if ($peticion->esperaJson()) return Respuesta::json(['error' => $e->getMessage()], 422);
            return $this->pantalla(['errorCliente' => $e->getMessage(), 'viejo' => $peticion->datos], 422);
        }
    }

    private function pantalla(array $datos = [], int $estado = 200): Respuesta
    {
        $q = $datos['q'] ?? '';
        return $this->vista('clientes/index', $datos + ['titulo' => 'Clientes', 'h1' => 'Clientes', 'modulo' => 'clientes',
            'css' => ['modulos.css'], 'clientes' => Cliente::listar($q), 'q' => $q, 'viejo' => []], $estado);
    }
}
