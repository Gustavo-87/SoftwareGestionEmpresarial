<?php

namespace App\Domain\GestionDocumental\Enums;

enum AmbitoDocumentoEnum: string
{
    case ORGANIZACION = 'organizacion';
    case COPROPIEDAD = 'copropiedad';
}
