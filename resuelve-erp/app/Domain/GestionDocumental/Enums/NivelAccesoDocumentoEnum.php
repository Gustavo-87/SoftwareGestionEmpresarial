<?php

namespace App\Domain\GestionDocumental\Enums;

enum NivelAccesoDocumentoEnum: string
{
    case ADMINISTRATIVO = 'administrativo';
    case INTERNO = 'interno';
    case COMUNIDAD = 'comunidad';
}
