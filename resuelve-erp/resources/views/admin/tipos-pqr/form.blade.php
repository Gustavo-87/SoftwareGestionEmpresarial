@extends('layouts.admin')
@section('titulo', $tipo->exists ? 'Editar tipo de PQRS' : 'Nuevo tipo de PQRS')
@section('eyebrow', 'Configuración PQRS')
@section('titulo_pagina', $tipo->exists ? 'Editar tipo de PQRS' : 'Nuevo tipo de PQRS')

@section('contenido')
<x-page-heading :title="$tipo->exists ? 'Editar tipo de PQRS' : 'Nuevo tipo de PQRS'" eyebrow="Configuración PQRS" />
<a class="back-link" href="{{ route('admin.tipos-pqr.index') }}">← Volver a Tipos de PQRS</a>

<form method="POST" action="{{ $tipo->exists ? route('admin.tipos-pqr.update', $tipo) : route('admin.tipos-pqr.store') }}">
    @csrf
    @if($tipo->exists) @method('PUT') @endif
    <div class="form-grid">
        <div class="field span-2">
            <label for="nombre">Nombre del tipo *</label>
            <input id="nombre" name="nombre" maxlength="100" value="{{ old('nombre', $tipo->nombre) }}" required>
            <span class="field-hint">Obligatorio y único. Ej.: Petición, Queja, Reclamo, Sugerencia.</span>
            @error('nombre') <span class="field-error">{{ $message }}</span> @enderror
        </div>
        <div class="field span-2">
            <label for="descripcion">Descripción</label>
            <textarea id="descripcion" name="descripcion" rows="3">{{ old('descripcion', $tipo->descripcion) }}</textarea>
            <span class="field-hint">Opcional.</span>
            @error('descripcion') <span class="field-error">{{ $message }}</span> @enderror
        </div>
    </div>
    <div class="form-actions">
        <a href="{{ route('admin.tipos-pqr.index') }}" class="button ghost">Cancelar</a>
        <button type="submit" class="button primary">{{ $tipo->exists ? 'Guardar cambios' : 'Crear tipo' }}</button>
    </div>
</form>
@endsection
