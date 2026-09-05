@extends('layouts.admin')

@section('titulo', 'Editar Copropiedad')
@section('titulo_pagina', 'Editar Copropiedad')
@section('eyebrow', 'Plataforma')

@section('contenido')
<a href="{{ route('admin.copropiedades.show', $copropiedad) }}" class="back-link">← Volver al detalle</a>

<div class="page-heading compact">
    <div>
        <h1>Editar Copropiedad</h1>
        <p>{{ $copropiedad->nombre }}</p>
    </div>
    <div class="heading-actions">
        <span class="status {{ $copropiedad->estado === 'activa' ? 'activo' : 'cerrada' }}">
            <i></i> {{ ucfirst($copropiedad->estado) }}
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
            <p>Modifica la información de la copropiedad.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.copropiedades.update', $copropiedad) }}">
        @csrf
        @method('PUT')
        <div class="form-grid">
            <div class="field span-2">
                <label for="nombre">Nombre *</label>
                <input type="text" name="nombre" id="nombre" value="{{ old('nombre', $copropiedad->nombre) }}" required maxlength="200">
                @error('nombre') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="nit">NIT</label>
                <input type="text" name="nit" id="nit" value="{{ old('nit', $copropiedad->nit) }}" maxlength="40">
                @error('nit') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="representante_legal">Representante legal</label>
                <input type="text" name="representante_legal" id="representante_legal" value="{{ old('representante_legal', $copropiedad->representante_legal) }}" maxlength="200">
                @error('representante_legal') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="ciudad">Ciudad</label>
                <input type="text" name="ciudad" id="ciudad" value="{{ old('ciudad', $copropiedad->ciudad) }}" maxlength="100">
                @error('ciudad') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="direccion">Dirección</label>
                <input type="text" name="direccion" id="direccion" value="{{ old('direccion', $copropiedad->direccion) }}" maxlength="300">
                @error('direccion') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="telefono">Teléfono</label>
                <input type="text" name="telefono" id="telefono" value="{{ old('telefono', $copropiedad->telefono) }}" maxlength="40">
                @error('telefono') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" value="{{ old('email', $copropiedad->email) }}" maxlength="150">
                @error('email') <span class="field-error">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="form-actions">
            <a href="{{ route('admin.copropiedades.show', $copropiedad) }}" class="button subtle">Cancelar</a>
            <div>
                <button type="submit" class="button primary">Guardar cambios</button>
            </div>
        </div>
    </form>
</div>
@endsection
