<?php
declare(strict_types=1);
namespace App\Services;
use DomainException;
final class ComprobanteQrDuplicado extends DomainException
{
    public function __construct(public readonly array $original)
    {
        parent::__construct('El comprobante QR ya fue utilizado. Revisa la venta original o ingresa otro comprobante.');
    }
}
