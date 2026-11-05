<?php

namespace App\Http\Requests;

use App\Application\TiposPqr\GestionarTipoPqr;
use App\Models\TipoPqr;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Edición de Tipo de PQRS (HU-CONF-03). La autorización (autoridad de
 * plataforma) precede a la validación; las reglas se reutilizan del caso de
 * uso (fuente única, sin duplicar).
 */
final class UpdateTipoPqrRequest extends FormRequest
{
    public function authorize(): bool
    {
        abort_unless($this->user()?->esAdministradorSistema() ?? false, 403);

        return true;
    }

    public function rules(): array
    {
        $tipo = $this->route('tipo');

        return GestionarTipoPqr::reglasValidacion($tipo instanceof TipoPqr ? $tipo->id : null);
    }

    public function messages(): array
    {
        return GestionarTipoPqr::mensajesValidacion();
    }
}
