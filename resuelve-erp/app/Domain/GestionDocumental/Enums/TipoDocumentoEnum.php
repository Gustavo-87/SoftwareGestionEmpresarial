<?php

namespace App\Domain\GestionDocumental\Enums;

enum TipoDocumentoEnum: string
{
    case DOCUMENTO_GENERAL = 'documento_general';
    case REGLAMENTO = 'reglamento';
    case MANUAL_CONVIVENCIA = 'manual_convivencia';
    case ACTA = 'acta';
}
