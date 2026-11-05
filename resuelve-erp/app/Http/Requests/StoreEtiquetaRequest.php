<?php

namespace App\Http\Requests;

use App\Application\Autorizacion\AutorizacionContextual;
use App\Application\Contexto\ContextoOperativo;
use App\Application\Pqrs\AdministrarCatalogoEtiquetasPqrs;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Alta de Etiqueta (HU-CONF-03). La autorización (`gestion.herramientas_gestionar`)
 * precede a la validación, cuyas reglas se movieron aquí desde el
 * controlador (fuente única, sin duplicar).
 */
class StoreEtiquetaRequest extends FormRequest
{
    public function authorize(): bool
    {
        abort_unless(app(AutorizacionContextual::class)->tienePermiso(app(ContextoOperativo::class), 'gestion.herramientas_gestionar'), 403);

        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => AdministrarCatalogoEtiquetasPqrs::normalizarNombre((string) $this->input('name')),
            'color' => AdministrarCatalogoEtiquetasPqrs::normalizarColor((string) $this->input('color')),
        ]);
    }

    public function rules(): array
    {
        $contexto = app(ContextoOperativo::class);

        return [
            'name' => ['required', 'string', 'max:60', $this->unicidad($contexto)],
            'color' => ['required', 'regex:/^#[0-9A-F]{6}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la etiqueta es obligatorio.',
            'name.max' => 'El nombre no puede superar 60 caracteres.',
            'name.unique' => 'Ya existe una etiqueta con este nombre en la Copropiedad. Reactívala si está desactivada.',
            'color.regex' => 'El color debe usar el formato #RRGGBB.',
        ];
    }

    protected function unicidad(ContextoOperativo $contexto): Unique
    {
        return Rule::unique('pqr_tags', 'name')->where(
            fn ($query) => $query
                ->where('organizacion_id', $contexto->organizacion->id)
                ->where('copropiedad_id', $contexto->copropiedad->id),
        );
    }
}
