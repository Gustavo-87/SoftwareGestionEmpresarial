@extends('layouts.admin')

@section('titulo', 'Editar Usuario')
@section('titulo_pagina', 'Editar Usuario')
@section('eyebrow', 'Plataforma')

@section('contenido')
<a href="{{ route('admin.usuarios-globales.show', $usuario) }}" class="back-link">← Volver al detalle</a>

<div class="page-heading compact">
    <div>
        <h1>Editar Usuario</h1>
        <p>{{ $usuario->name }}</p>
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
            <h2>Editar datos</h2>
            <p>Modifica la información del usuario.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.usuarios-globales.update', $usuario) }}">
        @csrf
        @method('PUT')
        <div class="form-grid">
            <div class="field">
                <label for="name">Nombre completo *</label>
                <input type="text" name="name" id="name" value="{{ old('name', $usuario->name) }}" required maxlength="150">
                @error('name') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="email">Email *</label>
                <input type="email" name="email" id="email" value="{{ old('email', $usuario->email) }}" required maxlength="150">
                @error('email') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="password">Nueva contraseña</label>
                <input type="password" name="password" id="password" minlength="8">
                <span class="field-hint">Dejar vacío para mantener la actual.</span>
                @error('password') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="tower">Torre/Bloque</label>
                <input type="text" name="tower" id="tower" value="{{ old('tower', $usuario->tower) }}" maxlength="50">
            </div>

            <div class="field">
                <label for="unit">Apartamento/Unidad</label>
                <input type="text" name="unit" id="unit" value="{{ old('unit', $usuario->unit) }}" maxlength="50">
            </div>

            <div class="field span-2">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="checkbox" name="es_administrador_sistema" value="1" {{ old('es_administrador_sistema', $usuario->es_administrador_sistema) ? 'checked' : '' }}>
                    <span>Administrador del sistema</span>
                </label>
            </div>
        </div>

        <div class="form-actions">
            <a href="{{ route('admin.usuarios-globales.show', $usuario) }}" class="button subtle">Cancelar</a>
            <div>
                <button type="submit" class="button primary">Guardar cambios</button>
            </div>
        </div>
    </form>
</div>
@endsection
