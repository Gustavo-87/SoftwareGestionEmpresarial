<?php

namespace App\Http\Requests;

/**
 * Edición de Persona (HU-CONF-03). Misma autorización y reglas que el alta
 * (la unicidad de la identificación la garantiza el caso de uso, que excluye
 * la propia persona).
 */
final class UpdatePersonaRequest extends StorePersonaRequest {}
