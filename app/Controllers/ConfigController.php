<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;
use App\Core\ErrorHttp;
use App\Core\Peticion;
use App\Core\Respuesta;
use App\Models\Bitacora;
use App\Models\Negocio;
use App\Services\Imagenes;
use DomainException;

/** Configuración del negocio (HU-08). Solo el administrador. */
final class ConfigController extends Controller
{
    private const LOGO_MAX = 1048576;

    public function index(Peticion $peticion): Respuesta
    {
        return $this->pantalla();
    }

    public function negocio(Peticion $peticion): Respuesta
    {
        $datos = [];
        foreach (Negocio::CAMPOS as $campo) {
            $datos[$campo] = (string) $peticion->entrada($campo);
        }
        $datos['nit'] = preg_replace('/\s+/', '', $datos['nit']);

        $errores = $this->validarNegocio($datos);
        $logo = $peticion->archivo('logo');
        $nuevoLogo = null;
        if (!$errores && $logo !== null) {
            try {
                $nuevoLogo = Imagenes::guardar($logo, 'logo', self::LOGO_MAX);
            } catch (DomainException $e) {
                $errores['n-logo'] = $e->getMessage();
            }
        }
        if ($errores) {
            return $this->pantalla(['errores' => $errores, 'negocio' => $datos + Negocio::obtener()], 422);
        }

        $antes = Negocio::obtener();
        Negocio::guardar($datos);
        if ($nuevoLogo !== null || $peticion->marcado('quitar_logo')) {
            Negocio::cambiarLogo($nuevoLogo);
            Imagenes::borrar($antes['logo']);
        }
        Bitacora::registrar('config.negocio', 'negocio', 1, $this->cambios($antes, $datos));
        return $this->redirigirConAviso('/config#negocio', 'Datos del negocio guardados.');
    }

    /** Logo del negocio (también lo usarán la factura y la pantalla del cliente). */
    public function logo(Peticion $peticion): Respuesta
    {
        $nombre = Negocio::obtener()['logo'] ?? null;
        $ruta = $nombre !== null ? Imagenes::ruta($nombre) : null;
        if ($ruta === null) {
            throw ErrorHttp::noEncontrado();
        }
        return new Respuesta((string) file_get_contents($ruta), 200, [
            'Content-Type' => Imagenes::tipo($nombre),
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    private function validarNegocio(array $d): array
    {
        $errores = [];
        $requeridos = [
            'razon_social' => ['n-razon', 'Escribe la razón social.', 120],
            'nombre_comercial' => ['n-comercial', 'Escribe el nombre comercial.', 80],
            'direccion' => ['n-direccion', 'Escribe la dirección.', 160],
            'municipio' => ['n-municipio', 'Escribe el municipio.', 60],
        ];
        foreach ($requeridos as $campo => [$id, $mensaje, $max]) {
            if ($d[$campo] === '') {
                $errores[$id] = $mensaje;
            } elseif (mb_strlen($d[$campo]) > $max) {
                $errores[$id] = "Usa hasta {$max} caracteres.";
            }
        }
        if (!preg_match('/^\d{7,13}$/', $d['nit'])) {
            $errores['n-nit'] = 'El NIT tiene solo números, entre 7 y 13 dígitos.';
        }
        if ($d['telefono'] !== '' && !preg_match('/^[0-9 +()-]{6,20}$/', $d['telefono'])) {
            $errores['n-telefono'] = 'Usa solo números, espacios y + ( ) -.';
        }
        return $errores;
    }

    /** Campos que cambiaron, con su valor anterior y el nuevo (para la bitácora). */
    private function cambios(array $antes, array $despues): array
    {
        $cambios = [];
        foreach ($despues as $campo => $valor) {
            if ((string) ($antes[$campo] ?? '') !== (string) $valor) {
                $cambios[$campo] = ['antes' => $antes[$campo] ?? null, 'despues' => $valor];
            }
        }
        return $cambios;
    }

    private function pantalla(array $datos = [], int $estado = 200): Respuesta
    {
        return $this->vista('config/index', $datos + [
            'titulo' => 'Configuración',
            'modulo' => 'config',
            'h1' => 'Configuración',
            'css' => ['modulos.css'],
            'js' => ['config.js'],
            'errores' => [],
            'negocio' => Negocio::obtener(),
            'leyendas' => (int) DB::valor('SELECT COUNT(*) FROM leyendas WHERE activo = 1'),
        ], $estado);
    }
}
