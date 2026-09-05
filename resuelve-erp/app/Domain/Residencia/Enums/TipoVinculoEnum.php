<?php

namespace App\Domain\Residencia\Enums;

enum TipoVinculoEnum: string
{
    case PROPIETARIO = 'propietario';
    case RESIDENTE = 'residente';
    case TENEDOR = 'tenedor';
}
