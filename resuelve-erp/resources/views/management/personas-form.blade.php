@extends('layouts.app')
@section('titulo', 'Editar persona')
@section('titulo_pagina', 'Editar persona')
@section('contenido')
<x-page-heading title="Editar persona" eyebrow="Residentes y unidades" compact>
    <x-slot name="actions"><a class="button secondary" href="{{ route('management.personas.index') }}">Volver a personas</a></x-slot>
</x-page-heading>
<section class="form-panel">
    <div class="form-intro"><div class="form-step">E</div><div><h2>Datos de la persona</h2><p>Registro de residencia de la Organización activa. El contexto se asigna en backend y los vínculos no se alteran al editar.</p></div></div>
    <form method="POST" action="{{ route('management.personas.update', $persona) }}">
        @csrf
        @method('PUT')
        <div class="form-grid">
            <div class="field">
                <label for="tipo_persona">Tipo de persona</label>
                <select id="tipo_persona" name="tipo_persona" required>
                    @foreach($tipos as $tipo)
                        <option value="{{ $tipo->value }}" @selected(old('tipo_persona', $persona->tipo_persona) === $tipo->value)>{{ ucfirst($tipo->value) }}</option>
                    @endforeach
                </select>
                @error('tipo_persona')<small class="field-error">{{ $message }}</small>@enderror
            </div>
            <div class="field">
                <label for="nombre_razon_social">Nombre / Razón social</label>
                <input id="nombre_razon_social" name="nombre_razon_social" value="{{ old('nombre_razon_social', $persona->nombre_razon_social) }}" required maxlength="255">
                @error('nombre_razon_social')<small class="field-error">{{ $message }}</small>@enderror
            </div>
            <div class="field">
                <label for="identificacion">Identificación</label>
                <input id="identificacion" name="identificacion" value="{{ old('identificacion', $persona->identificacion) }}" maxlength="50">
                @error('identificacion')<small class="field-error">{{ $message }}</small>@enderror
            </div>
            <div class="field">
                <label for="email">Correo electrónico</label>
                <input id="email" name="email" type="email" value="{{ old('email', $persona->email) }}" maxlength="255">
                @error('email')<small class="field-error">{{ $message }}</small>@enderror
            </div>
            <div class="field">
                <label for="telefono">Teléfono</label>
                <input id="telefono" name="telefono" value="{{ old('telefono', $persona->telefono) }}" maxlength="50">
                @error('telefono')<small class="field-error">{{ $message }}</small>@enderror
            </div>
        </div>
        <div class="form-actions">
            <div>
                <a class="button subtle" href="{{ route('management.personas.index') }}">Cancelar</a>
                <button class="button primary">Guardar cambios</button>
            </div>
        </div>
    </form>
</section>
@endsection
