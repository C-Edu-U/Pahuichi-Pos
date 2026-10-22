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
        return $this->vista('facturas/index', ['titulo' => 'Ventas y facturas', 'h1' => 'Ventas y facturas', 'modulo' => 'ventas', 'css' => ['modulos.css'],
            'facturas' => Factura::ultimas(puede('ventas.todas') ? null : Auth::id())]);
    }

    public function mostrar(Peticion $peticion): Respuesta
    {
        $f = $this->permitida((int) $peticion->parametro('id'));
        $s = $f['snapshot'];
        return $this->vista('facturas/tickets', ['titulo' => 'Factura N° ' . $f['numero'], 'h1' => 'Pedido ' . $s['pedido'] . ' · factura N° ' . $f['numero'],
            'modulo' => 'ventas', 'claseBody' => 'panel--tickets', 'css' => ['modulos.css', 'tickets.css', 'tickets-hu10.css'],
            'js' => ['qrcode.js', 'tickets.js', 'anular.js'], 'f' => $f, 's' => $s, 'perfilesTickets' => ConfiguracionTickets::todas()]);
    }

    /** Entrega un PDF real; nunca abre un diálogo ni envía trabajos a una impresora. */
    public function pdf(Peticion $peticion): Respuesta
    {
        $f = $this->permitida((int) $peticion->parametro('id'));
        $destino = $peticion->consulta('destino', 'caja');
        $soloFactura = $peticion->consulta('solo') === 'factura';
        if ($soloFactura && $destino !== 'caja') throw new ErrorHttp(422, 'La factura individual utiliza el perfil de caja.');
        try {
            $salida = TicketsPdf::preparar($destino, $soloFactura);
        } catch (DomainException $e) {
            throw new ErrorHttp(422, $e->getMessage());
        }
        $bytes = TicketsPdf::generar($f['snapshot'], $salida);
        $nombre = 'Tickets-' . $destino . '-factura-' . (int) $f['numero'] . '.pdf';
        $disposicion = $peticion->consulta('descargar') === '1' ? 'attachment' : 'inline';
        return new Respuesta($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposicion . '; filename="' . $nombre . '"',
            'Content-Length' => (string) strlen($bytes),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function reimprimir(Peticion $peticion): Respuesta
    {
        $f = $this->permitida((int) $peticion->parametro('id'));
        Bitacora::registrar('factura.reimpresion', 'facturas', $f['id'], ['tipo' => 'tres_tickets', 'medio' => 'navegador', 'accion' => 'solicitud']);
        return Respuesta::json(['aviso' => 'Reimpresión solicitada.']);
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
