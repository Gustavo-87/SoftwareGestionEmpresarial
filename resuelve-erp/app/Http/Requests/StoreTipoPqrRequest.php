<?php

namespace App\Http\Requests;

use App\Application\TiposPqr\GestionarTipoPqr;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Alta de Tipo de PQRS (HU-CONF-03). La autorización (autoridad de
 * plataforma) precede a la validación; las reglas se reutilizan del caso de
 * uso (fuente única, sin duplicar).
 */
final class StoreTipoPqrRequest extends FormRequest
{
    public function authorize(): bool
    {
        abort_unless($this->user()?->esAdministradorSistema() ?? false, 403);

        return true;
    }

    public function rules(): array
    {
        return GestionarTipoPqr::reglasValidacion();
    }

    public function messages(): array
    {
        return GestionarTipoPqr::mensajesValidacion();
    }
}
