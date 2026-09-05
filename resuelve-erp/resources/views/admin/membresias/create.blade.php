@extends('layouts.admin')

@section('titulo', 'Crear Membresía')
@section('titulo_pagina', 'Crear Membresía')

@section('contenido')
<a href="{{ route('admin.membresias.index') }}" class="back-link">← Volver a membresías</a>

<div class="page-heading compact">
    <div>
        <h1>Crear Membresía</h1>
        <p>Asociar un usuario a una copropiedad dentro de una organización.</p>
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
            <h2>Datos de la membresía</h2>
            <p>Selecciona el usuario, organización y copropiedad.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.membresias.store') }}">
        @csrf
        <div class="form-grid">
            <div class="field">
                <label for="usuario_id">Usuario *</label>
                <select name="usuario_id" id="usuario_id" required>
                    <option value="">Seleccionar usuario...</option>
                    @foreach($usuarios as $usuario)
                        <option value="{{ $usuario->id }}" {{ old('usuario_id') == $usuario->id ? 'selected' : '' }}>
                            {{ $usuario->name }} ({{ $usuario->email }})
                        </option>
                    @endforeach
                </select>
                @error('usuario_id') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="organizacion_id">Organización *</label>
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
                <label for="copropiedad_id">Copropiedad *</label>
                <select name="copropiedad_id" id="copropiedad_id" required>
                    <option value="">Seleccionar copropiedad...</option>
                    @foreach($copropiedades as $copropiedad)
                        <option value="{{ $copropiedad->id }}" {{ old('copropiedad_id') == $copropiedad->id ? 'selected' : '' }}>
                            {{ $copropiedad->nombre }}
                        </option>
                    @endforeach
                </select>
                @error('copropiedad_id') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="vigente_desde">Vigente desde *</label>
                <input type="date" name="vigente_desde" id="vigente_desde" value="{{ old('vigente_desde', date('Y-m-d')) }}" required>
                @error('vigente_desde') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="vigente_hasta">Vigente hasta</label>
                <input type="date" name="vigente_hasta" id="vigente_hasta" value="{{ old('vigente_hasta') }}">
                <span class="field-hint">Dejar vacío para membresía indefinida.</span>
                @error('vigente_hasta') <span class="field-error">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="form-actions">
            <a href="{{ route('admin.membresias.index') }}" class="button subtle">Cancelar</a>
            <div>
                <button type="submit" class="button primary">Crear Membresía</button>
            </div>
        </div>
    </form>
</div>
@endsection
