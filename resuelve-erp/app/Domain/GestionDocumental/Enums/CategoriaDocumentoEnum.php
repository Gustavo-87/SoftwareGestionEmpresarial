<?php

namespace App\Domain\GestionDocumental\Enums;

enum CategoriaDocumentoEnum: string
{
    case NORMATIVO = 'normativo';
    case ADMINISTRATIVO = 'administrativo';
    case GOBIERNO_COPROPIEDAD = 'gobierno_copropiedad';
    case CONTRACTUAL = 'contractual';
    case FINANCIERO = 'financiero';
    case COMUNICACIONES = 'comunicaciones';
    case OTRO = 'otro';
}
