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
        return $this->vista('facturas/index', ['titulo' => 'Ventas y facturas', 'h1' => 'Ventas y facturas', 'modulo' => 'ventas', 'css' => ['modulos.css'],
            'facturas' => Factura::ultimas(puede('ventas.todas') ? null : Auth::id())]);
    }

    public function mostrar(Peticion $peticion): Respuesta
    {
        $f = $this->permitida((int) $peticion->parametro('id'));
        $modo = $peticion->consulta('reimpresion', '0');
        if (!in_array($modo, ['0', '1'], true)) throw new ErrorHttp(422, 'Modo de tickets no válido.');
        $reimpresion = $modo === '1';
        if ($reimpresion) {
            try { ReimpresionService::validar($f); }
            catch (DomainException $e) { throw new ErrorHttp(422, $e->getMessage()); }
        }
        $respuesta = $this->pantallaTickets($f, $reimpresion);
        ReimpresionService::cambiarVista($f, $reimpresion);
        return $respuesta;
    }

    /** Compatibilidad con enlaces de la revisión anterior. */
    public function originales(Peticion $peticion): Respuesta
    {
        $f = $this->permitida((int) $peticion->parametro('id'));
        return Respuesta::redirigir('/facturas/' . $f['id']);
    }

    public function accesoOriginal(Peticion $peticion): Respuesta
    {
        return $this->originales($peticion);
    }

    private function pantallaTickets(array $f, bool $reimpresion = false): Respuesta
    {
        $s = $f['snapshot'];
        return $this->vista('facturas/tickets', ['titulo' => 'Factura N° ' . $f['numero'], 'h1' => 'Pedido ' . $s['pedido'] . ' · factura N° ' . $f['numero'],
            'modulo' => 'ventas', 'claseBody' => 'panel--tickets', 'css' => ['modulos.css', 'tickets.css', 'tickets-hu10.css'],
            'js' => ['qrcode.js', 'tickets.js', 'anular.js'], 'f' => $f, 's' => $s,
            'perfilesTickets' => ConfiguracionTickets::todas(), 'reimpresion' => $reimpresion]);
    }

    /** Las antiguas solicitudes de originales usan el mismo perfil que la barra superior. */
    public function original(Peticion $peticion): Respuesta
    {
        $f = $this->permitida((int) $peticion->parametro('id'));
        try { $pdf = EntregaInicialService::generar($f, $peticion->entrada('destino')); }
        catch (DomainException $e) { throw new ErrorHttp(422, $e->getMessage()); }
        return $this->entregarPdf($pdf, $peticion->entrada('descargar') === '1');
    }

    public function pdf(Peticion $peticion): Respuesta
    {
        $f = $this->permitida((int) $peticion->parametro('id'));
        $destino = $peticion->consulta('destino', 'caja');
        $soloFactura = $peticion->consulta('solo') === 'factura';
        $modo = $peticion->consulta('reimpresion', '0');
        if (!in_array($modo, ['0', '1'], true)) throw new ErrorHttp(422, 'Modo de tickets no válido.');
        if ($soloFactura && $destino !== 'caja') throw new ErrorHttp(422, 'La factura individual utiliza el perfil de caja.');
        try {
            $seleccion = $peticion->consulta('seleccion');
            if (array_key_exists('seleccion', $peticion->query) && $seleccion === '') throw new DomainException('Elige una opción de la lista de documentos.');
            if (!in_array($peticion->consulta('descargar', '0'), ['0', '1'], true)) throw new DomainException('Elige Ver PDF o Descargar PDF.');
            $salida = $seleccion !== '' ? TicketsPdf::prepararSeleccion($seleccion) : TicketsPdf::preparar($destino, $soloFactura);
            $salida['descargar'] = $peticion->consulta('descargar') === '1';
            $pdf = $modo === '1' ? ReimpresionService::generarLote($f, $salida)
                : EntregaInicialService::generarDocumento($f, $salida);
        } catch (DomainException $e) {
            throw new ErrorHttp(422, $e->getMessage());
        }
        return $this->entregarPdf($pdf, $peticion->consulta('descargar') === '1');
    }

    public function reimprimir(Peticion $peticion): Respuesta
    {
        $f = $this->permitida((int) $peticion->parametro('id'));
        try {
            $pdf = ReimpresionService::generar($f, $peticion->entrada('tipo', 'copia'), $peticion->entrada('descargar') === '1');
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
