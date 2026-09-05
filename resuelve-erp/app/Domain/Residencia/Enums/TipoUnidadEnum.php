<?php

namespace App\Domain\Residencia\Enums;

enum TipoUnidadEnum: string
{
    case APARTAMENTO = 'apartamento';
    case CASA = 'casa';
    case LOCAL = 'local';
    case OFICINA = 'oficina';
    case DEPOSITO = 'deposito';
    case PARQUEADERO = 'parqueadero';
}
