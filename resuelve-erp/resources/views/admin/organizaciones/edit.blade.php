@extends('layouts.admin')

@section('titulo', 'Editar Organización')
@section('titulo_pagina', 'Editar Organización')
@section('eyebrow', 'Plataforma')

@section('contenido')
<a href="{{ route('admin.organizaciones.show', $organizacion) }}" class="back-link">← Volver al detalle</a>

<div class="page-heading compact">
    <div>
        <h1>Editar Organización</h1>
        <p>{{ $organizacion->nombre }}</p>
    </div>
    <div class="heading-actions">
        <span class="status {{ $organizacion->estado === 'activa' ? 'activo' : 'cerrada' }}">
            <i></i> {{ ucfirst($organizacion->estado) }}
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
            <h2>Editar datos</h2>
            <p>Modifica la información de la organización.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.organizaciones.update', $organizacion) }}">
        @csrf
        @method('PUT')
        <div class="form-grid">
            <div class="field span-2">
                <label for="nombre">Nombre *</label>
                <input type="text" name="nombre" id="nombre" value="{{ old('nombre', $organizacion->nombre) }}" required maxlength="200">
                @error('nombre') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="identificacion_tributaria">NIT</label>
                <input type="text" name="identificacion_tributaria" id="identificacion_tributaria" value="{{ old('identificacion_tributaria', $organizacion->identificacion_tributaria) }}" maxlength="40">
                @error('identificacion_tributaria') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" value="{{ old('email', $organizacion->email) }}" maxlength="150">
                @error('email') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="telefono">Teléfono</label>
                <input type="text" name="telefono" id="telefono" value="{{ old('telefono', $organizacion->telefono) }}" maxlength="40">
                @error('telefono') <span class="field-error">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="form-actions">
            <a href="{{ route('admin.organizaciones.show', $organizacion) }}" class="button subtle">Cancelar</a>
            <div>
                <button type="submit" class="button primary">Guardar cambios</button>
            </div>
        </div>
    </form>
</div>
@endsection
