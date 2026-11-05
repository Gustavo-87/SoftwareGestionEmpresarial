<?php

namespace App\Http\Requests;

use App\Application\Contexto\ContextoOperativo;
use App\Application\Residencia\AutorizacionResidencia;
use App\Application\Residencia\GestionarPersona;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Alta de Persona (HU-CONF-03). La autorización (`residentes.gestionar`)
 * precede a la validación; las reglas se reutilizan del caso de uso (fuente
 * única, sin duplicar).
 */
class StorePersonaRequest extends FormRequest
{
    public function authorize(): bool
    {
        AutorizacionResidencia::exigirGestion(app(ContextoOperativo::class));

        return true;
    }

    public function rules(): array
    {
        return GestionarPersona::reglasValidacion();
    }

    public function messages(): array
    {
        return GestionarPersona::mensajesValidacion();
    }
}
