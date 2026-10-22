<?php

declare(strict_types=1);
namespace App\Services;

use App\Core\DB;
use App\Core\Vista;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Dompdf\Dompdf;
use Dompdf\Options;
use App\Models\Bitacora;
use App\Models\ConfiguracionTickets;
use DomainException;

/** HU-12: valida los perfiles y selecciona los tickets; el contenido sigue siendo el snapshot. */
final class TicketsPdf
{
    public static function guardar(string $destino, array $datos): void
    {
        if (!isset(ConfiguracionTickets::DESTINOS[$destino])) throw new DomainException('Elige Caja o Cocina.');
        $d = [];
        foreach (['ancho', 'columnas', 'copias'] as $campo) {
            $v = $datos[$campo] ?? null;
            if (!is_string($v) || !preg_match('/^[1-9][0-9]{0,2}$/D', $v)) throw new DomainException('Ancho, columnas y copias deben ser números enteros.');
            $d[$campo] = (int) $v;
        }
        if (!in_array($d['ancho'], [58, 76], true)) throw new DomainException('El ancho debe ser de 58 o 76 mm.');
        if ($d['columnas'] < 24 || $d['columnas'] > 48) throw new DomainException('Usa entre 24 y 48 columnas.');
        if ($d['copias'] < 1 || $d['copias'] > 3) throw new DomainException('Elige de 1 a 3 copias de cada ticket.');
        foreach (array_keys(ConfiguracionTickets::TIPOS) as $tipo) {
            $v = $datos[$tipo] ?? '0';
            if (!is_string($v) || !in_array($v, ['0', '1'], true)) throw new DomainException('Revisa los tipos de tickets seleccionados.');
            $d[$tipo] = (int) $v;
        }
        if ($d['factura'] + $d['copia'] + $d['comanda'] === 0) throw new DomainException('Selecciona al menos un tipo de ticket para este destino.');
        DB::transaccion(static function () use ($destino, $d): void {
            $antes = DB::fila('SELECT * FROM configuracion_tickets WHERE destino=? FOR UPDATE', [$destino]);
            if (!$antes) throw new DomainException('No se encontró el perfil. Revisa las migraciones.');
            ConfiguracionTickets::guardar($destino, $d);
            Bitacora::registrar('config.tickets', 'configuracion_tickets', null, ['destino' => $destino, 'antes' => $antes, 'despues' => $d]);
        });
    }

    public static function preparar(string $destino, bool $soloFactura = false): array
    {
        $p = ConfiguracionTickets::todas()[$destino] ?? throw new DomainException('Elige Caja o Cocina.');
        $tipos = [];
        foreach (array_keys(ConfiguracionTickets::TIPOS) as $tipo) {
            if ($p[$tipo] && (!$soloFactura || $tipo === 'factura')) $tipos[] = $tipo;
        }
        if (!$tipos) throw new DomainException('La factura no está seleccionada en el perfil de caja. Solicita al administrador que la habilite.');
        return ['perfil' => $p, 'tipos' => $tipos];
    }

    /** El snapshot aporta los datos; el perfil vigente define la presentación. */
    public static function generar(array $snapshot, array $salida): string
    {
        $qr = '';
        $enlaceQr = '';
        if (in_array('factura', $salida['tipos'], true)) {
            $ruta = '/verificar?' . http_build_query([
                'nit' => $snapshot['negocio']['nit'], 'cuf' => $snapshot['cuf'],
                'numero' => $snapshot['factura'], 'monto' => $snapshot['total'],
            ]);
            // Origen del servidor actual: permite verificar desde el mismo equipo o la red local.
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8443';
            if (!preg_match('/^[a-zA-Z0-9.\[\]:-]+$/D', $host)) $host = 'localhost:8443';
            $esHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            $enlaceQr = ($esHttps ? 'https://' : 'http://') . $host . $ruta;
            $writer = new Writer(new ImageRenderer(new RendererStyle(240, 4), new SvgImageBackEnd()));
            $qr = 'data:image/svg+xml;base64,' . base64_encode($writer->writeString($enlaceQr));
        }
        $html = Vista::render('facturas/pdf', $salida + ['s' => $snapshot, 'qr' => $qr, 'enlaceQr' => $enlaceQr], null);
        $ancho = (int) $salida['perfil']['ancho'] * 72 / 25.4;
        // Primero mide el contenido, después crea las hojas del rollo sin recortar los tickets.
        $medida = self::motor($ancho, 14400);
        $alto = 0.0;
        $medida->setCallbacks([['event' => 'end_frame', 'f' => static function ($frame) use (&$alto): void {
            $nodo = $frame->get_node();
            if ($nodo instanceof \DOMElement && $nodo->tagName === 'article') {
                $alto = max($alto, (float) $frame->get_border_box()['h']);
            }
        }]]);
        $medida->loadHtml($html, 'UTF-8');
        $medida->render();
        unset($medida);
        $pdf = self::motor($ancho, min(14400, max(100, ceil($alto + 8 * 72 / 25.4))));
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->render();
        return $pdf->output();
    }

    private static function motor(float $ancho, float $alto): Dompdf
    {
        $opciones = new Options();
        $opciones->set('isRemoteEnabled', false);
        $opciones->set('isPhpEnabled', false);
        $opciones->set('isJavascriptEnabled', false);
        $opciones->set('defaultFont', 'DejaVu Sans Mono');
        $pdf = new Dompdf($opciones);
        $pdf->setPaper([0, 0, $ancho, $alto]);
        return $pdf;
    }
}
