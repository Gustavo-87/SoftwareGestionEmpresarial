@extends('layouts.admin')

@section('titulo', 'Editar Membresía')
@section('titulo_pagina', 'Editar Membresía')

@section('contenido')
<a href="{{ route('admin.membresias.show', $membresia) }}" class="back-link">← Volver al detalle</a>

<div class="page-heading compact">
    <div>
        <h1>Editar Membresía</h1>
        <p>{{ $membresia->usuario->name ?? 'N/A' }} · {{ $membresia->copropiedad->nombre ?? 'N/A' }}</p>
    </div>
    <div class="heading-actions">
        <span class="status {{ $membresia->estado }}">
            <i></i> {{ ucfirst($membresia->estado) }}
        </span>
    </div>
</div>

@if($errors->any())
    <x-notice variant="error" title="Se encontraron errores:">
        <ul style="margin:4px 0 0;padding-left:17px;">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </x-notice>
@endif


<div class="form-panel">
    <div class="form-intro">
        <div class="form-step">✎</div>
        <div>
            <h2>Editar vigencia</h2>
            <p>Modifica las fechas de vigencia de la membresía.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.membresias.update', $membresia) }}">
        @csrf
        @method('PUT')
        <div class="form-grid">
            <div class="field">
                <label for="vigente_desde">Vigente desde *</label>
                <input type="date" name="vigente_desde" id="vigente_desde"
                       value="{{ old('vigente_desde', $membresia->vigente_desde->format('Y-m-d')) }}" required>
                @error('vigente_desde') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="vigente_hasta">Vigente hasta</label>
                <input type="date" name="vigente_hasta" id="vigente_hasta"
                       value="{{ old('vigente_hasta', $membresia->vigente_hasta?->format('Y-m-d')) }}">
                <span class="field-hint">Dejar vacío para membresía indefinida.</span>
                @error('vigente_hasta') <span class="field-error">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="form-actions">
            <a href="{{ route('admin.membresias.show', $membresia) }}" class="button subtle">Cancelar</a>
            <div>
                <button type="submit" class="button primary">Guardar Cambios</button>
            </div>
        </div>
    </form>
</div>
@endsection
