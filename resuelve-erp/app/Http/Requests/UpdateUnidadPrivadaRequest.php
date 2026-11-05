<?php

namespace App\Http\Requests;

/**
 * Edición de Unidad Privada (HU-CONF-03). Misma autorización y reglas que el
 * alta (la unicidad del código la garantiza el caso de uso y el índice único).
 */
final class UpdateUnidadPrivadaRequest extends StoreUnidadPrivadaRequest {}
