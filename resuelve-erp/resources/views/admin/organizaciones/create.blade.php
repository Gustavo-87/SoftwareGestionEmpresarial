@extends('layouts.admin')

@section('titulo', 'Crear Organización')
@section('titulo_pagina', 'Crear Organización')
@section('eyebrow', 'Plataforma')

@section('contenido')
<a href="{{ route('admin.organizaciones.index') }}" class="back-link">← Volver a organizaciones</a>

<div class="page-heading compact">
    <div>
        <h1>Crear Organización</h1>
        <p>Registrar una nueva organización en la plataforma.</p>
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
        <div class="form-step">1</div>
        <div>
            <h2>Datos de la organización</h2>
            <p>Completa la información básica de la organización.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.organizaciones.store') }}">
        @csrf
        <div class="form-grid">
            <div class="field span-2">
                <label for="nombre">Nombre *</label>
                <input type="text" name="nombre" id="nombre" value="{{ old('nombre') }}" required maxlength="200" placeholder="Ej: Administra Todo S.A.S.">
                @error('nombre') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="identificacion_tributaria">NIT</label>
                <input type="text" name="identificacion_tributaria" id="identificacion_tributaria" value="{{ old('identificacion_tributaria') }}" maxlength="40" placeholder="900.000.000-0">
                <span class="field-hint">Opcional. Único si se proporciona.</span>
                @error('identificacion_tributaria') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" value="{{ old('email') }}" maxlength="150" placeholder="contacto@empresa.com">
                @error('email') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="telefono">Teléfono</label>
                <input type="text" name="telefono" id="telefono" value="{{ old('telefono') }}" maxlength="40" placeholder="+57 300 123 4567">
                @error('telefono') <span class="field-error">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="form-actions">
            <a href="{{ route('admin.organizaciones.index') }}" class="button subtle">Cancelar</a>
            <div>
                <button type="submit" class="button primary">Crear organización</button>
            </div>
        </div>
    </form>
</div>
@endsection
