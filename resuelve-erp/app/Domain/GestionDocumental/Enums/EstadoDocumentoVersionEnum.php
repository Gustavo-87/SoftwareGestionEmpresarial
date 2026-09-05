<?php

namespace App\Domain\GestionDocumental\Enums;

enum EstadoDocumentoVersionEnum: string
{
    case BORRADOR = 'borrador';
    case PENDIENTE_APROBACION = 'pendiente_aprobacion';
    case APROBADA = 'aprobada';
    case RECHAZADA = 'rechazada';
}
