@extends('layouts.admin')

@section('titulo', 'Crear Usuario')
@section('titulo_pagina', 'Crear Usuario')
@section('eyebrow', 'Plataforma')

@section('contenido')
<a href="{{ route('admin.usuarios-globales.index') }}" class="back-link">← Volver a usuarios</a>

<div class="page-heading compact">
    <div>
        <h1>Crear Usuario</h1>
        <p>Registrar un nuevo usuario en la plataforma.</p>
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
            <h2>Datos del usuario</h2>
            <p>Completa la información básica del usuario.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.usuarios-globales.store') }}">
        @csrf
        <div class="form-grid">
            <div class="field">
                <label for="name">Nombre completo *</label>
                <input type="text" name="name" id="name" value="{{ old('name') }}" required maxlength="150">
                @error('name') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="email">Email *</label>
                <input type="email" name="email" id="email" value="{{ old('email') }}" required maxlength="150">
                @error('email') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="password">Contraseña *</label>
                <input type="password" name="password" id="password" required minlength="8">
                <span class="field-hint">Mínimo 8 caracteres.</span>
                @error('password') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="password_confirmation">Confirmar contraseña *</label>
                <input type="password" name="password_confirmation" id="password_confirmation" required minlength="8">
            </div>

            <div class="field">
                <label for="tower">Torre/Bloque</label>
                <input type="text" name="tower" id="tower" value="{{ old('tower') }}" maxlength="50">
                <span class="field-hint">Opcional.</span>
            </div>

            <div class="field">
                <label for="unit">Apartamento/Unidad</label>
                <input type="text" name="unit" id="unit" value="{{ old('unit') }}" maxlength="50">
                <span class="field-hint">Opcional.</span>
            </div>

            <div class="field span-2">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="checkbox" name="es_administrador_sistema" value="1" {{ old('es_administrador_sistema') ? 'checked' : '' }}>
                    <span>Administrador del sistema</span>
                </label>
                <span class="field-hint">Otorga acceso al área de administración de la plataforma.</span>
            </div>
        </div>

        <div class="form-actions">
            <a href="{{ route('admin.usuarios-globales.index') }}" class="button subtle">Cancelar</a>
            <div>
                <button type="submit" class="button primary">Crear usuario</button>
            </div>
        </div>
    </form>
</div>
@endsection
