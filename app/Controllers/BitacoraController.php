<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Peticion;
use App\Core\Respuesta;
use App\Models\Bitacora;
use App\Services\BitacoraTexto;
use DateTimeImmutable;

/** Bitácora (HU-05): consulta de solo lectura, solo el administrador. */
final class BitacoraController extends Controller
{
    private const POR_PAGINA = 50;

    public function index(Peticion $peticion): Respuesta
    {
        $filtro = [
            'desde' => $this->fecha($peticion->consulta('desde')),
            'hasta' => $this->fecha($peticion->consulta('hasta')),
            'usuario' => $peticion->consulta('usuario'),
            'accion' => $peticion->consulta('accion'),
            'buscar' => mb_substr($peticion->consulta('buscar'), 0, 80),
        ];
        if ($filtro['usuario'] !== 'ninguno' && !ctype_digit($filtro['usuario'])) {
            $filtro['usuario'] = '';
        }
        $acciones = $filtro['accion'] !== '' ? BitacoraTexto::accionesDeFiltro($filtro['accion']) : null;
        if ($acciones === null) {
            $filtro['accion'] = '';
        }
        if ($filtro['desde'] !== '' && $filtro['hasta'] !== '' && $filtro['desde'] > $filtro['hasta']) {
            [$filtro['desde'], $filtro['hasta']] = [$filtro['hasta'], $filtro['desde']];
        }

        $pagina = max(1, (int) $peticion->consulta('pagina', '1'));
        [$filas, $total] = Bitacora::buscar($filtro + ['acciones' => $acciones ?? []], $pagina, self::POR_PAGINA);
        $paginas = max(1, (int) ceil($total / self::POR_PAGINA));

        return $this->vista('bitacora/index', [
            'titulo' => 'Bitácora',
            'modulo' => 'bitacora',
            'h1' => 'Bitácora',
            'css' => ['modulos.css'],
            'filas' => $filas,
            'total' => $total,
            'pagina' => min($pagina, $paginas),
            'paginas' => $paginas,
            'filtro' => $filtro,
            'filtrando' => array_filter($filtro) !== [],
            'usuarios' => Bitacora::usuarios(),
            'opciones' => BitacoraTexto::opcionesFiltro(),
        ]);
    }

    /** Fecha del filtro (AAAA-MM-DD) o vacío si no es válida. */
    private function fecha(string $valor): string
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
        return $d !== false && $d->format('Y-m-d') === $valor ? $valor : '';
    }
}
