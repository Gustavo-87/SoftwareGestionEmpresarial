<?php

namespace App\Domain\GestionDocumental\Enums;

enum EstadoDocumentoEnum: string
{
    case ACTIVO = 'activo';
    case ARCHIVADO = 'archivado';
}
