@extends('layouts.app')
@section('titulo', 'Solicitud de mantenimiento')
@section('titulo_pagina', 'Solicitud de mantenimiento')
@section('contenido')
<x-page-heading :title="$mantenimiento->titulo" eyebrow="Gestión de mantenimiento" compact>
    <x-slot name="actions"><a class="button secondary" href="{{ route('mantenimiento.index') }}">Volver a Mantenimiento</a></x-slot>
</x-page-heading>
<div class="maintenance-layout">
<section class="panel maintenance-summary"><h2>Detalle de la solicitud</h2><p class="maintenance-description">{{ $mantenimiento->descripcion }}</p>
<dl class="maintenance-facts"><div><dt>Solicitante</dt><dd>{{ $mantenimiento->solicitante->name }}</dd></div><div><dt>Estado</dt><dd>{{ $mantenimiento->etiquetaEstado() }}</dd></div><div><dt>Responsable</dt><dd>{{ $mantenimiento->responsable?->name ?? 'Sin asignar' }}</dd></div><div><dt>Fecha programada</dt><dd>{{ $mantenimiento->fecha_programada?->format('d/m/Y') ?? 'Sin programar' }}</dd></div>@if($mantenimiento->finalizado_at)<div><dt>Finalizado</dt><dd>{{ $mantenimiento->finalizado_at->format('d/m/Y H:i') }}</dd></div>@endif</dl></section>
@can('update', $mantenimiento)
<section class="panel maintenance-management"><h2>Gestionar solicitud</h2><p class="maintenance-help">Asigna el responsable, programa la fecha y actualiza el estado.</p><form method="POST" action="{{ route('mantenimiento.update', $mantenimiento) }}">@csrf @method('PUT')
<div class="field"><label for="responsable_id">Responsable</label><select id="responsable_id" name="responsable_id"><option value="">Sin asignar</option>@foreach($responsables as $responsable)<option value="{{ $responsable->id }}" @selected((string) old('responsable_id', $mantenimiento->responsable_id) === (string) $responsable->id)>{{ $responsable->name }}</option>@endforeach</select>@error('responsable_id')<small class="field-error">{{ $message }}</small>@enderror</div>
<div class="field"><label for="fecha_programada">Fecha programada</label><input id="fecha_programada" type="date" name="fecha_programada" value="{{ old('fecha_programada', $mantenimiento->fecha_programada?->format('Y-m-d')) }}">@error('fecha_programada')<small class="field-error">{{ $message }}</small>@enderror</div>
<div class="field"><label for="estado">Estado</label><select id="estado" name="estado" required>@foreach(['pendiente' => 'Pendiente', 'en_proceso' => 'En proceso', 'finalizado' => 'Finalizado'] as $valor => $etiqueta)<option value="{{ $valor }}" @selected(old('estado', $mantenimiento->estado) === $valor)>{{ $etiqueta }}</option>@endforeach</select>@error('estado')<small class="field-error">{{ $message }}</small>@enderror</div>
<button class="button primary">Guardar cambios</button></form></section>
@endcan
</div>

<section class="panel maintenance-summary" aria-labelledby="pqrs-origen-title">
    <h2 id="pqrs-origen-title">PQRS de origen</h2>
    @if($mantenimiento->pqr)
        <dl class="maintenance-facts">
            <div><dt>PQRS</dt><dd><a href="{{ route('pqrs.show', $mantenimiento->pqr) }}">PQR-{{ str_pad($mantenimiento->pqr->id, 4, '0', STR_PAD_LEFT) }}</a></dd></div>
            <div><dt>Asunto</dt><dd>{{ $mantenimiento->pqr->asunto }}</dd></div>
            <div><dt>Estado de la PQRS</dt><dd>{{ $mantenimiento->pqr->estado }}</dd></div>
        </dl>
        @if($gestiona && $mantenimiento->estado !== 'finalizado')
            <form method="POST" action="{{ route('mantenimiento.pqr.update', $mantenimiento) }}" class="maintenance-help">@csrf @method('PATCH')
                <input type="hidden" name="pqr_id" value="">
                <button class="button subtle">Retirar vínculo con la PQRS</button>
            </form>
        @endif
    @else
        <p class="maintenance-help">Sin PQRS de origen: este mantenimiento es independiente.</p>
        @if($gestiona && $mantenimiento->estado !== 'finalizado')
            @if($pqrsVinculables->isEmpty())
                <p class="maintenance-help">No hay PQRS que puedas vincular en esta Copropiedad.</p>
            @else
                <form method="POST" action="{{ route('mantenimiento.pqr.update', $mantenimiento) }}">@csrf @method('PATCH')
                    <div class="field"><label for="pqr_id">Vincular a PQRS</label>
                        <select id="pqr_id" name="pqr_id" required>
                            <option value="">Selecciona una PQRS</option>
                            @foreach($pqrsVinculables as $pqrOpcion)
                                <option value="{{ $pqrOpcion->id }}">PQR-{{ str_pad($pqrOpcion->id, 4, '0', STR_PAD_LEFT) }} · {{ $pqrOpcion->asunto }}</option>
                            @endforeach
                        </select>
                        @error('pqr_id')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                    <button class="button primary">Vincular a PQRS</button>
                </form>
            @endif
        @endif
    @endif
</section>

<section class="panel maintenance-summary" aria-labelledby="evidencia-title">
    <h2 id="evidencia-title">Evidencia documental</h2>
    <p class="maintenance-help">La evidencia fija la versión exacta utilizada; el ciclo documental del documento no se modifica.</p>
    <table class="data" style="width:100%;border-collapse:collapse">
            <thead><tr><th>Documento</th><th>Versión</th><th>Estado de versión</th>@if($gestiona)<th>Acciones</th>@endif</tr></thead>
            <tbody>
            @forelse($evidencias as $evidencia)
                <tr>
                    <td>{{ $evidencia->documento?->titulo ?? 'Documento no disponible' }}@if($evidencia->documento?->estado->value === 'archivado') <span class="type-label">Archivado</span>@endif</td>
                    <td>{{ $evidencia->version?->numero ?? '—' }}</td>
                    <td><span class="status {{ $evidencia->version?->estado->value }}"><i></i>{{ $evidencia->version?->estado->value ?? '—' }}</span></td>
                    @if($gestiona)
                        <td>
                            @if($mantenimiento->estado !== 'finalizado')
                                <form method="POST" action="{{ route('mantenimiento.evidencia.destroy', [$mantenimiento, $evidencia]) }}">@csrf @method('DELETE')
                                    <button class="button subtle danger" data-confirm="Retirar evidencia" data-confirm-message="La evidencia se retirará del mantenimiento. El documento conserva su ciclo documental.">Retirar</button>
                                </form>
                            @else
                                <span class="type-label">Inmutable</span>
                            @endif
                        </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="{{ $gestiona ? 4 : 3 }}"><div class="empty-state" style="padding:14px"><p>Sin evidencia documental vinculada.</p></div></td></tr>
            @endforelse
            </tbody>
        </table>
    @if($gestiona && $mantenimiento->estado !== 'finalizado')
        @if(($evidenciasDisponibles ?? collect())->isEmpty())
            <p class="maintenance-help">No hay versiones aprobadas de documentos activos disponibles para vincular.</p>
        @else
            <form method="POST" action="{{ route('mantenimiento.evidencia.store', $mantenimiento) }}">@csrf
                <div class="field"><label for="documento_version_id">Vincular versión aprobada como evidencia</label>
                    <select id="documento_version_id" name="documento_version_id" required>
                        <option value="">Selecciona una versión</option>
                        @foreach($evidenciasDisponibles as $opcion)
                            <option value="{{ $opcion['version_id'] }}">{{ $opcion['documento'] }} — versión {{ $opcion['numero'] }}</option>
                        @endforeach
                    </select>
                    @error('documento_version_id')<small class="field-error">{{ $message }}</small>@enderror
                    @error('evidencia')<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <button class="button primary">Vincular evidencia</button>
            </form>
        @endif
    @endif
</section>

<section class="panel maintenance-summary" aria-labelledby="resultado-title">
    <h2 id="resultado-title">Resultado del mantenimiento</h2>
    @if($mantenimiento->resultado_publico || ($puedeVerInterno && $mantenimiento->resultado_interno))
        <dl class="maintenance-facts">
            <div><dt>Resultado público</dt><dd>{{ $mantenimiento->resultado_publico ?? '—' }}</dd></div>
            @if($puedeVerInterno)
                <div><dt>Resultado interno</dt><dd>{{ $mantenimiento->resultado_interno ?? '—' }}</dd></div>
            @endif
            @if($mantenimiento->resultado_registrado_at)
                <div><dt>Registrado</dt><dd>{{ $mantenimiento->resultado_registrado_at->format('d/m/Y H:i') }}{{ $mantenimiento->resultadoRegistrador ? ' · '.$mantenimiento->resultadoRegistrador->name : '' }}</dd></div>
            @endif
        </dl>
    @else
        <div class="empty-state" style="padding:14px"><p>{{ $mantenimiento->resultado_publico ? 'El resultado interno está reservado al personal autorizado.' : 'Sin resultado registrado.' }}</p></div>
    @endif

    @if($gestiona && $mantenimiento->estado !== 'finalizado')
        <form method="POST" action="{{ route('mantenimiento.resultado.update', $mantenimiento) }}">@csrf @method('PATCH')
            <div class="field"><label for="resultado_publico">Resultado público</label><textarea id="resultado_publico" name="resultado_publico" rows="3" placeholder="Información que podrá consultar el radicador">{{ old('resultado_publico', $mantenimiento->resultado_publico) }}</textarea>@error('resultado_publico')<small class="field-error">{{ $message }}</small>@enderror</div>
            <div class="field"><label for="resultado_interno">Resultado interno</label><textarea id="resultado_interno" name="resultado_interno" rows="3" placeholder="Información reservada al personal autorizado">{{ old('resultado_interno', $mantenimiento->resultado_interno) }}</textarea>@error('resultado_interno')<small class="field-error">{{ $message }}</small>@enderror</div>
            <button class="button primary">Registrar resultado</button>
        </form>

        <form method="POST" action="{{ route('mantenimiento.finalizar', $mantenimiento) }}" style="margin-top:14px">@csrf @method('PATCH')
            <button class="button primary" data-confirm="Finalizar mantenimiento" data-confirm-message="El mantenimiento quedará finalizado. La PQRS no se cerrará automáticamente: registra la respuesta y el cierre desde su expediente.">Finalizar mantenimiento</button>
            <p class="maintenance-help">Requiere resultado público y al menos una evidencia con versión aprobada. La PQRS no se cierra automáticamente.</p>
            @error('estado')<small class="field-error">{{ $message }}</small>@enderror
            @error('evidencia')<small class="field-error">{{ $message }}</small>@enderror
        </form>
    @elseif($mantenimiento->estado === 'finalizado')
        <p class="maintenance-help">Mantenimiento finalizado el {{ $mantenimiento->finalizado_at?->format('d/m/Y H:i') }}. Su resultado y sus evidencias son inmutables; la PQRS se gestiona por separado en su expediente.</p>
    @endif
</section>
@endsection
