<?php

namespace App\Http\Requests;

use App\Application\Contexto\ContextoOperativo;
use App\Application\Residencia\AutorizacionResidencia;
use App\Application\Residencia\GestionarUnidadPrivada;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Alta de Unidad Privada (HU-CONF-03). La autorización (`residentes.gestionar`)
 * precede a la validación; las reglas se reutilizan del caso de uso (fuente
 * única, sin duplicar).
 */
class StoreUnidadPrivadaRequest extends FormRequest
{
    public function authorize(): bool
    {
        AutorizacionResidencia::exigirGestion(app(ContextoOperativo::class));

        return true;
    }

    public function rules(): array
    {
        return GestionarUnidadPrivada::reglasValidacion();
    }

    public function messages(): array
    {
        return GestionarUnidadPrivada::mensajesValidacion();
    }
}
