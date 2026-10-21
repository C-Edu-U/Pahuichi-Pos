<?php

declare(strict_types=1);

namespace App\Services;

use DomainException;

/** La autorización con PIN no existe, venció, ya se usó o no corresponde a la acción. */
final class AutorizacionInvalida extends DomainException
{
}
