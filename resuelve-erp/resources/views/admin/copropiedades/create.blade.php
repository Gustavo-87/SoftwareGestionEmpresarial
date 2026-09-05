@extends('layouts.admin')

@section('titulo', 'Crear Copropiedad')
@section('titulo_pagina', 'Crear Copropiedad')
@section('eyebrow', 'Plataforma')

@section('contenido')
<a href="{{ route('admin.copropiedades.index') }}" class="back-link">← Volver a copropiedades</a>

<div class="page-heading compact">
    <div>
        <h1>Crear Copropiedad</h1>
        <p>Registrar una nueva copropiedad dentro de una organización.</p>
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
            <h2>Datos de la copropiedad</h2>
            <p>Completa la información de la copropiedad y selecciona la organización padre.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.copropiedades.store') }}">
        @csrf
        <div class="form-grid">
            <div class="field">
                <label for="organizacion_id">Organización padre *</label>
                <select name="organizacion_id" id="organizacion_id" required>
                    <option value="">Seleccionar organización...</option>
                    @foreach($organizaciones as $organizacion)
                        <option value="{{ $organizacion->id }}" {{ old('organizacion_id') == $organizacion->id ? 'selected' : '' }}>
                            {{ $organizacion->nombre }}
                        </option>
                    @endforeach
                </select>
                @error('organizacion_id') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="nombre">Nombre *</label>
                <input type="text" name="nombre" id="nombre" value="{{ old('nombre') }}" required maxlength="200" placeholder="Ej: Conjunto Residencial Los Robles">
                @error('nombre') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="nit">NIT</label>
                <input type="text" name="nit" id="nit" value="{{ old('nit') }}" maxlength="40" placeholder="900.000.000-0">
                <span class="field-hint">Opcional. Único por organización.</span>
                @error('nit') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="representante_legal">Representante legal</label>
                <input type="text" name="representante_legal" id="representante_legal" value="{{ old('representante_legal') }}" maxlength="200">
                @error('representante_legal') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="ciudad">Ciudad</label>
                <input type="text" name="ciudad" id="ciudad" value="{{ old('ciudad') }}" maxlength="100" placeholder="Bogotá">
                @error('ciudad') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="direccion">Dirección</label>
                <input type="text" name="direccion" id="direccion" value="{{ old('direccion') }}" maxlength="300">
                @error('direccion') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="telefono">Teléfono</label>
                <input type="text" name="telefono" id="telefono" value="{{ old('telefono') }}" maxlength="40">
                @error('telefono') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" value="{{ old('email') }}" maxlength="150">
                @error('email') <span class="field-error">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="form-actions">
            <a href="{{ route('admin.copropiedades.index') }}" class="button subtle">Cancelar</a>
            <div>
                <button type="submit" class="button primary">Crear copropiedad</button>
            </div>
        </div>
    </form>
</div>
@endsection
