@extends('layouts.app')
@section('titulo', 'Solicitud de mantenimiento')
@section('titulo_pagina', 'Solicitud de mantenimiento')
@section('contenido')
<x-page-heading :title="$mantenimiento->titulo" eyebrow="Gestión de mantenimiento" compact>
    <x-slot name="actions"><a class="button secondary" href="{{ route('mantenimiento.index') }}">Volver a Mantenimiento</a></x-slot>
</x-page-heading>
<div class="maintenance-layout">
<section class="panel maintenance-summary"><h2>Detalle de la solicitud</h2><p class="maintenance-description">{{ $mantenimiento->descripcion }}</p>
<dl class="maintenance-facts"><div><dt>Solicitante</dt><dd>{{ $mantenimiento->solicitante->name }}</dd></div><div><dt>Estado</dt><dd>{{ $mantenimiento->etiquetaEstado() }}</dd></div><div><dt>Responsable</dt><dd>{{ $mantenimiento->responsable?->name ?? 'Sin asignar' }}</dd></div><div><dt>Fecha programada</dt><dd>{{ $mantenimiento->fecha_programada?->format('d/m/Y') ?? 'Sin programar' }}</dd></div></dl></section>
@can('update', $mantenimiento)
<section class="panel maintenance-management"><h2>Gestionar solicitud</h2><p class="maintenance-help">Asigna el responsable, programa la fecha y actualiza el estado.</p><form method="POST" action="{{ route('mantenimiento.update', $mantenimiento) }}">@csrf @method('PUT')
<div class="field"><label for="responsable_id">Responsable</label><select id="responsable_id" name="responsable_id"><option value="">Sin asignar</option>@foreach($responsables as $responsable)<option value="{{ $responsable->id }}" @selected((string) old('responsable_id', $mantenimiento->responsable_id) === (string) $responsable->id)>{{ $responsable->name }}</option>@endforeach</select>@error('responsable_id')<small class="field-error">{{ $message }}</small>@enderror</div>
<div class="field"><label for="fecha_programada">Fecha programada</label><input id="fecha_programada" type="date" name="fecha_programada" value="{{ old('fecha_programada', $mantenimiento->fecha_programada?->format('Y-m-d')) }}">@error('fecha_programada')<small class="field-error">{{ $message }}</small>@enderror</div>
<div class="field"><label for="estado">Estado</label><select id="estado" name="estado" required>@foreach(['pendiente' => 'Pendiente', 'en_proceso' => 'En proceso', 'finalizado' => 'Finalizado'] as $valor => $etiqueta)<option value="{{ $valor }}" @selected(old('estado', $mantenimiento->estado) === $valor)>{{ $etiqueta }}</option>@endforeach</select>@error('estado')<small class="field-error">{{ $message }}</small>@enderror</div>
<button class="button primary">Guardar cambios</button></form></section>
@endcan
</div>
@endsection
