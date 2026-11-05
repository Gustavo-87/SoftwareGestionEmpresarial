@extends('layouts.app')
@section('titulo', 'Unidad '.$unidad->codigo)
@section('titulo_pagina', 'Unidad '.$unidad->codigo)
@section('contenido')
<x-page-heading :title="'Unidad '.$unidad->codigo" eyebrow="Residentes y unidades" compact>
    <x-slot name="actions"><a class="button secondary" href="{{ route('management.unidades.edit', $unidad) }}">Editar unidad</a><a class="button secondary" href="{{ route('management.unidades.index') }}">Volver a unidades</a></x-slot>
</x-page-heading>

<section class="panel maintenance-summary" style="margin-bottom:18px">
    <h2>Detalle de la unidad</h2>
    <dl class="maintenance-facts">
        <div><dt>Código</dt><dd>{{ $unidad->codigo }}</dd></div>
        <div><dt>Nombre / Número</dt><dd>{{ $unidad->numero_nombre ?: '—' }}</dd></div>
        <div><dt>Torre / Bloque</dt><dd>{{ $unidad->torre_bloque ?: '—' }}</dd></div>
        <div><dt>Tipo</dt><dd>{{ $unidad->tipo ? ucfirst($unidad->tipo->value) : '—' }}</dd></div>
        <div><dt>Estado</dt><dd>{{ $unidad->estado === 'activa' ? 'Activa' : 'Desactivada' }}</dd></div>
    </dl>
    @if($unidad->estado !== 'activa')
        <div class="locked-note" role="status"><span>!</span><p><strong>Unidad desactivada</strong>No admite nuevas vinculaciones. Sus vínculos vigentes e históricos se conservan sin cambios y no se concluyen automáticamente.</p></div>
    @endif
</section>

<section class="panel maintenance-summary" style="margin-bottom:18px" aria-labelledby="asociar-title">
    <h2 id="asociar-title">Asociar persona</h2>
    <p class="maintenance-help">Los vínculos residenciales no conceden acceso al sistema ni modifican membresías o permisos.</p>
    @error('vinculo')<small class="field-error" role="alert">{{ $message }}</small>@enderror
    @if($unidad->estado !== 'activa')
        <p class="maintenance-help">La unidad está desactivada: no se admite la asociación de personas.</p>
    @elseif($personas->isEmpty() && $qPersona === '')
        <p class="maintenance-help">No hay personas activas en la Organización. Registra una persona para poder asociarla.</p>
    @else
        <form method="GET" action="{{ route('management.unidades.show', $unidad) }}" class="filters" style="margin-bottom:10px">
            <div class="search-box"><input type="search" name="q_persona" value="{{ $qPersona }}" placeholder="Buscar por nombre o identificación" aria-label="Buscar personas"></div>
            <button class="button subtle">Buscar</button>
        </form>
        <form method="POST" action="{{ route('management.unidades.vinculos.store', $unidad) }}">@csrf
            <div class="field">
                <label for="persona_id">Persona (activas de la Organización)</label>
                <select id="persona_id" name="persona_id" required>
                    <option value="">Selecciona una persona</option>
                    @foreach($personas as $persona)
                        <option value="{{ $persona->id }}" @selected(old('persona_id') == $persona->id)>{{ $persona->nombre_razon_social }}{{ $persona->identificacion ? ' · '.$persona->identificacion : '' }}</option>
                    @endforeach
                </select>
                <small class="field-help">Mostrando {{ $personas->firstItem() ?? 0 }}–{{ $personas->lastItem() ?? 0 }} de {{ $personas->total() }} personas activas.</small>
                {{ $personas->links() }}
                @error('persona_id')<small class="field-error">{{ $message }}</small>@enderror
            </div>
            <div class="field">
                <label for="tipo_vinculo">Tipo de vínculo</label>
                <select id="tipo_vinculo" name="tipo_vinculo" required>
                    <option value="propietario" @selected(old('tipo_vinculo') === 'propietario')>Propietario</option>
                    <option value="residente" @selected(old('tipo_vinculo') === 'residente')>Residente</option>
                    <option value="tenedor" @selected(old('tipo_vinculo') === 'tenedor')>Tenedor</option>
                </select>
                @error('tipo_vinculo')<small class="field-error">{{ $message }}</small>@enderror
            </div>
            <div class="field">
                <label for="vigente_desde">Vigente desde</label>
                <input id="vigente_desde" type="date" name="vigente_desde" value="{{ old('vigente_desde', now()->format('Y-m-d')) }}" required>
                @error('vigente_desde')<small class="field-error">{{ $message }}</small>@enderror
            </div>
            <div class="field">
                <label for="vigente_hasta">Vigente hasta (opcional)</label>
                <input id="vigente_hasta" type="date" name="vigente_hasta" value="{{ old('vigente_hasta') }}">
                <small class="field-help">Vacío significa vigencia abierta; puedes concluir el vínculo después.</small>
                @error('vigente_hasta')<small class="field-error">{{ $message }}</small>@enderror
            </div>
            <div class="field">
                <label for="observacion">Observación (opcional)</label>
                <textarea id="observacion" name="observacion" rows="2">{{ old('observacion') }}</textarea>
                @error('observacion')<small class="field-error">{{ $message }}</small>@enderror
            </div>
            <button class="button primary">Asociar persona</button>
        </form>
    @endif
    @include('management.partials.persona-form')
</section>

<section class="panel users-panel" style="margin-bottom:18px" aria-labelledby="vigentes-title">
    <div class="panel-header"><h2 id="vigentes-title">Vínculos vigentes</h2></div>
    <div class="table-wrap"><table>
        <thead><tr><th>Persona</th><th>Tipo</th><th>Vigente desde</th><th>Vigente hasta</th><th>Observación</th><th></th></tr></thead>
        <tbody>
        @forelse($vigentes as $vinculo)
            <tr>
                <td><strong>{{ $vinculo->persona?->nombre_razon_social ?? 'Persona no disponible' }}</strong>@if($vinculo->persona && $vinculo->persona->estado !== 'activa') <span class="type-label">Persona inactiva</span>@endif</td>
                <td><span class="type-label">{{ ucfirst($vinculo->tipo_vinculo->value) }}</span></td>
                <td>{{ $vinculo->vigente_desde->format('d/m/Y') }}</td>
                <td>{{ $vinculo->vigente_hasta ? $vinculo->vigente_hasta->format('d/m/Y') : 'Abierta' }}</td>
                <td>{{ $vinculo->observacion ?: '—' }}</td>
                <td class="actions">
                    <details class="document-action"><summary class="button subtle">Corregir fechas</summary>
                        <form method="POST" action="{{ route('management.unidades.vinculos.corregir', [$unidad, $vinculo]) }}">@csrf @method('PATCH')
                            <div class="field"><label>Vigente desde</label><input type="date" name="vigente_desde" value="{{ $vinculo->vigente_desde->format('Y-m-d') }}" required></div>
                            <div class="field"><label>Vigente hasta</label><input type="date" name="vigente_hasta" value="{{ $vinculo->vigente_hasta?->format('Y-m-d') }}"></div>
                            <div class="field"><label>Motivo de la corrección</label><textarea name="motivo" rows="2" required placeholder="Justifica la corrección"></textarea></div>
                            <button class="button primary" data-confirm="Corregir vigencia" data-confirm-message="La corrección registrará en la auditoría los valores anteriores, los nuevos y el motivo.">Guardar corrección</button>
                        </form>
                    </details>
                    <form method="POST" action="{{ route('management.unidades.vinculos.concluir', [$unidad, $vinculo]) }}">@csrf @method('PATCH')
                        <input type="date" name="vigente_hasta" aria-label="Fecha de conclusión del vínculo con {{ $vinculo->persona?->nombre_razon_social }}" value="{{ now()->format('Y-m-d') }}">
                        <button class="button subtle danger" data-confirm="Concluir vínculo" data-confirm-message="El vínculo pasará al historial conservando todos sus datos. No se elimina ningún registro.">Concluir</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="6"><x-empty-state title="Sin vínculos vigentes" description="Asocia una persona con un periodo de vigencia para comenzar." /></td></tr>
        @endforelse
        </tbody>
    </table></div>
</section>

<section class="panel users-panel" aria-labelledby="historial-title">
    <div class="panel-header"><h2 id="historial-title">Historial de vínculos</h2></div>
    <div class="table-wrap"><table>
        <thead><tr><th>Persona</th><th>Tipo</th><th>Vigente desde</th><th>Vigente hasta</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        @forelse($historicos as $vinculo)
            <tr>
                <td><strong>{{ $vinculo->persona?->nombre_razon_social ?? 'Persona no disponible' }}</strong>@if($vinculo->persona && $vinculo->persona->estado !== 'activa') <span class="type-label">Persona inactiva</span>@endif</td>
                <td><span class="type-label">{{ ucfirst($vinculo->tipo_vinculo->value) }}</span></td>
                <td>{{ $vinculo->vigente_desde->format('d/m/Y') }}</td>
                <td>{{ $vinculo->vigente_hasta ? $vinculo->vigente_hasta->format('d/m/Y') : '—' }}</td>
                <td><span class="status archivado"><i></i>{{ $vinculo->estado === 'concluido' ? 'Concluido' : 'Vigencia finalizada' }}</span></td>
                <td class="actions">
                    <details class="document-action"><summary class="button subtle">Corregir fechas</summary>
                        <form method="POST" action="{{ route('management.unidades.vinculos.corregir', [$unidad, $vinculo]) }}">@csrf @method('PATCH')
                            <div class="field"><label>Vigente desde</label><input type="date" name="vigente_desde" value="{{ $vinculo->vigente_desde->format('Y-m-d') }}" required></div>
                            <div class="field"><label>Vigente hasta</label><input type="date" name="vigente_hasta" value="{{ $vinculo->vigente_hasta?->format('Y-m-d') }}" {{ $vinculo->estado === 'concluido' ? 'required' : '' }}></div>
                            <div class="field"><label>Motivo de la corrección</label><textarea name="motivo" rows="2" required placeholder="Justifica la corrección"></textarea></div>
                            <button class="button primary" data-confirm="Corregir vigencia" data-confirm-message="La corrección registrará en la auditoría los valores anteriores, los nuevos y el motivo.">Guardar corrección</button>
                        </form>
                    </details>
                </td>
            </tr>
        @empty
            <tr><td colspan="6"><x-empty-state title="Sin vínculos en el historial" description="Los vínculos concluidos aparecerán aquí." /></td></tr>
        @endforelse
        </tbody>
    </table></div>
</section>
@endsection
