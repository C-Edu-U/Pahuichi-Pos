<?php

declare(strict_types=1);
namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;
use App\Core\ErrorHttp;
use App\Core\Peticion;
use App\Core\Respuesta;
use App\Core\Reloj;
use App\Services\QrDiarioService;
use DomainException;

final class QrController extends Controller
{
    public function index(Peticion $p): Respuesta
    {
        return $this->pantalla();
    }
    private function pantalla(array $datos = [], int $estado = 200): Respuesta
    {
        return $this->vista('qr/index', $datos + ['titulo' => 'QR del día', 'h1' => 'QR del día', 'modulo' => 'qr', 'css' => ['modulos.css', 'qr-dia.css'], 'js' => con_demostraciones() ? ['qr-dia.js', 'qr-comprobantes-demo.js'] : ['qr-dia.js'], 'erroresQr' => [], 'valoresQr' => []], $estado);
    }
    public function estado(Peticion $p): Respuesta
    {
        return Respuesta::json(QrDiarioService::estado())->cabecera('Cache-Control', 'no-store');
    }
    public function imagen(Peticion $p): Respuesta
    {
        $q = QrDiarioService::estado();
        if (!$q['vigente'] || (string) $q['id'] !== $p->parametro('id')) throw ErrorHttp::noEncontrado();
        $f = DB::fila('SELECT imagen, imagen_tipo FROM qr_diarios WHERE id = ?', [$q['id']]);
        return new Respuesta($f['imagen'], 200, ['Content-Type' => $f['imagen_tipo'], 'Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
    public function simular(Peticion $p): Respuesta
    {
        if (!con_demostraciones()) throw ErrorHttp::noEncontrado();
        try {
            $accion = $p->entrada('accion');
            $id = filter_var($p->entrada('qr_id'), FILTER_VALIDATE_INT);
            if (!is_string($accion) || !$id) throw new DomainException('Selecciona el QR del día.');
            return Respuesta::json(QrDiarioService::simular($accion, (int) $id))->cabecera('Cache-Control', 'no-store');
        } catch (DomainException $e) {
            return Respuesta::json(['error' => $e->getMessage()], 422)->cabecera('Cache-Control', 'no-store');
        }
    }
    public function demostrarComprobante(Peticion $p): Respuesta
    {
        if (!con_demostraciones()) throw ErrorHttp::noEncontrado();
        try {
            $ref=$p->entrada('comprobante');$accion=$p->entrada('accion');
            if (!is_string($ref) || !is_string($accion)) throw new DomainException('Escribe el comprobante de una venta QR de hoy.');
            return Respuesta::json(\App\Services\ComprobanteQrService::demostrar($ref, $accion))->cabecera('Cache-Control', 'no-store');
        } catch (DomainException $e) {
            return Respuesta::json(['error'=>$e->getMessage()], 422)->cabecera('Cache-Control', 'no-store');
        }
    }
    public function subir(Peticion $p): Respuesta
    {
        $datos = [];
        foreach (['fecha', 'monto'] as $campo) {
            $valor = $p->entrada($campo);
            $datos[$campo] = is_string($valor) ? $valor : '';
        }
        try {
            QrDiarioService::validar($datos['fecha'], $datos['monto']);
        } catch (DomainException $e) {
            $campo = $datos['fecha'] !== Reloj::ahora()->format('Y-m-d') ? 'qr-fecha' : 'qr-monto';
            if ($p->esperaJson()) return Respuesta::json(['errores' => [$campo => $e->getMessage()]], 422);
            return $this->pantalla(['erroresQr' => [$campo => $e->getMessage()], 'valoresQr' => $datos], 422);
        }
        try {
            QrDiarioService::subir($datos['fecha'], $datos['monto'], $p->archivo('qr'));
        } catch (DomainException $e) {
            if ($p->esperaJson()) return Respuesta::json(['errores' => ['qr-imagen' => $e->getMessage()]], 422);
            return $this->pantalla(['erroresQr' => ['qr-imagen' => $e->getMessage()], 'valoresQr' => $datos], 422);
        }
        $respuesta = $this->redirigirConAviso('/qr', 'QR del día guardado. Vigente hasta las 23:59:59.');
        return $p->esperaJson() ? Respuesta::json(['guardado' => true]) : $respuesta;
    }
}
