<?php

declare(strict_types=1);
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\ErrorHttp;
use App\Core\Peticion;
use App\Core\Respuesta;
use App\Models\Bitacora;
use App\Models\Factura;
use App\Models\ConfiguracionTickets;
use App\Services\TicketsPdf;
use App\Services\ReimpresionService;
use App\Services\EntregaInicialService;
use DomainException;

final class FacturaController extends Controller
{
    private function permitida(int $id): array
    {
        $f = Factura::buscar($id) ?? throw ErrorHttp::noEncontrado('Esa factura no existe.');
        if (!puede('ventas.todas') && (int) $f['cajero_id'] !== Auth::id()) {
            Bitacora::registrar('acceso.denegado', 'facturas', $id);
            throw new ErrorHttp(403);
        }
        return $f;
    }

    public function index(Peticion $peticion): Respuesta
    {
        EntregaInicialService::cerrarTodas();
        return $this->vista('facturas/index', ['titulo' => 'Ventas y facturas', 'h1' => 'Ventas y facturas', 'modulo' => 'ventas', 'css' => ['modulos.css'],
            'facturas' => Factura::ultimas(puede('ventas.todas') ? null : Auth::id())]);
    }

    public function mostrar(Peticion $peticion): Respuesta
    {
        $f = $this->permitida((int) $peticion->parametro('id'));
        EntregaInicialService::cerrar((int) $f['id']);
        return $this->pantallaTickets($f);
    }

    public function originales(Peticion $peticion): Respuesta
    {
        $f = $this->permitida((int) $peticion->parametro('id'));
        $token = $peticion->consulta('entrega');
        if (!EntregaInicialService::vigente((int) $f['id'], $token)) return Respuesta::redirigir('/facturas/' . $f['id']);
        return $this->pantallaTickets($f, true, $token);
    }

    public function accesoOriginal(Peticion $peticion): Respuesta
    {
        $f = $this->permitida((int) $peticion->parametro('id'));
        if (!EntregaInicialService::puedeConsultarOriginal()) throw new ErrorHttp(403);
        try {
            $token = EntregaInicialService::consultarOriginal($f);
        } catch (DomainException $e) {
            throw new ErrorHttp(422, $e->getMessage());
        }
        return Respuesta::redirigir('/facturas/' . $f['id'] . '/originales?entrega=' . rawurlencode($token));
    }

    private function pantallaTickets(array $f, bool $inicial = false, string $token = ''): Respuesta
    {
        $s = $f['snapshot'];
        return $this->vista('facturas/tickets', ['titulo' => 'Factura N° ' . $f['numero'], 'h1' => 'Pedido ' . $s['pedido'] . ' · factura N° ' . $f['numero'],
            'modulo' => 'ventas', 'claseBody' => 'panel--tickets', 'css' => ['modulos.css', 'tickets.css', 'tickets-hu10.css'],
            'js' => ['qrcode.js', 'tickets.js', 'anular.js'], 'f' => $f, 's' => $s, 'perfilesTickets' => ConfiguracionTickets::todas(),
            'consultaOriginal' => $inicial && !empty(\App\Core\Sesion::get('entregas_iniciales', [])[(int) $f['id']]['consulta_original']),
            'puedeConsultarOriginal' => EntregaInicialService::puedeConsultarOriginal() && $f['estado'] === 'emitida',
            'entregaInicial' => $inicial, 'tokenEntrega' => $token, 'reimpresion' => !$inicial,
            'entregados' => EntregaInicialService::entregados((int) $f['id'])]);
    }

    public function original(Peticion $peticion): Respuesta
    {
        $f = $this->permitida((int) $peticion->parametro('id'));
        $token = $peticion->entrada('entrega');
        try {
            if (!is_string($token)) throw new DomainException('El permiso de entrega no es válido.');
            $pdf = EntregaInicialService::generar($f, $peticion->entrada('destino'), $token);
        } catch (DomainException $e) {
            throw new ErrorHttp(422, $e->getMessage());
        }
        return $this->entregarPdf($pdf, $peticion->entrada('descargar') === '1');
    }

    /** Compatibilidad: las URL antiguas también regeneran con marca y bitácora, nunca otro original. */
    public function pdf(Peticion $peticion): Respuesta
    {
        $f = $this->permitida((int) $peticion->parametro('id'));
        $destino = $peticion->consulta('destino', 'caja');
        $soloFactura = $peticion->consulta('solo') === 'factura';
        if ($soloFactura && $destino !== 'caja') throw new ErrorHttp(422, 'La factura individual utiliza el perfil de caja.');
        try {
            $salida = TicketsPdf::preparar($destino, $soloFactura);
            EntregaInicialService::cerrar((int) $f['id']);
            $pdf = ReimpresionService::generarLote($f, $salida);
        } catch (DomainException $e) {
            throw new ErrorHttp(422, $e->getMessage());
        }
        return $this->entregarPdf($pdf, $peticion->consulta('descargar') === '1');
    }

    public function reimprimir(Peticion $peticion): Respuesta
    {
        $f = $this->permitida((int) $peticion->parametro('id'));
        try {
            EntregaInicialService::cerrar((int) $f['id']);
            $pdf = ReimpresionService::generar($f, $peticion->entrada('tipo', 'copia'));
        } catch (DomainException $e) {
            throw new ErrorHttp(422, $e->getMessage());
        }
        return $this->entregarPdf($pdf, $peticion->entrada('descargar') === '1');
    }

    private function entregarPdf(array $pdf, bool $descargar): Respuesta
    {
        $disposicion = $descargar ? 'attachment' : 'inline';
        return new Respuesta($pdf['bytes'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposicion . '; filename="' . $pdf['nombre'] . '"',
            'Content-Length' => (string) strlen($pdf['bytes']),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Verificación pública: exige los cuatro datos que contiene el QR propio. */
    public function verificar(Peticion $peticion): Respuesta
    {
        $cuf = $peticion->consulta('cuf');
        $nit = $peticion->consulta('nit');
        $numero = $peticion->consulta('numero');
        $monto = $peticion->consulta('monto');
        if (!preg_match('/^[A-F0-9]{28}$/D', $cuf) || !preg_match('/^\d{7,13}$/D', $nit) || !preg_match('/^[1-9]\d{0,17}$/D', $numero) || !preg_match('/^\d{1,8}\.\d{2}$/D', $monto)) throw ErrorHttp::noEncontrado();
        $f = Factura::verificar($cuf, $nit, $numero, $monto) ?? throw ErrorHttp::noEncontrado('Los datos no corresponden a una factura.');
        return $this->vista('facturas/verificar', ['titulo' => 'Verificar factura', 'f' => $f], 200, 'layouts/simple');
    }
}
