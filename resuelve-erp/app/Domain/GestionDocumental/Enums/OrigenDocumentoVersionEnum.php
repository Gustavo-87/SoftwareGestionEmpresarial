<?php

namespace App\Domain\GestionDocumental\Enums;

enum OrigenDocumentoVersionEnum: string
{
    case USUARIO = 'usuario';
    case SISTEMA = 'sistema';
    case IMPORTADO = 'importado';
}
