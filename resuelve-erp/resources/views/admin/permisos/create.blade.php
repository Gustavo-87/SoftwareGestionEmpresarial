@extends('layouts.admin')

@section('titulo', 'Nuevo permiso')
@section('eyebrow', 'Administración del sistema')
@section('titulo_pagina', 'Nuevo permiso')

@section('contenido')

<a class="back-link" href="{{ route('admin.permisos.index') }}">← Volver al catálogo de permisos</a>

<section class="form-panel">
    <div class="form-intro"><span class="form-step">01</span><div><h2>Datos del permiso</h2><p>La clave sigue el formato «modulo.accion» en minúsculas. El rol admin base hereda el permiso nuevo automáticamente.</p></div></div>

    <form method="POST" action="{{ route('admin.permisos.store') }}">
        @csrf
        <div class="form-grid">
            <div class="field">
                <label for="permiso-clave">Clave (modulo.accion)</label>
                <input id="permiso-clave" name="clave" value="{{ old('clave') }}" required pattern="[a-z0-9_]+\.[a-z0-9_]+" placeholder="documentos.exportar">
                @error('clave')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="field">
                <label for="permiso-nombre">Nombre</label>
                <input id="permiso-nombre" name="nombre" value="{{ old('nombre') }}" required maxlength="150">
                @error('nombre')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="field">
                <label for="permiso-modulo">Módulo</label>
                <input id="permiso-modulo" name="modulo" value="{{ old('modulo') }}" required maxlength="100" placeholder="documentos">
                @error('modulo')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="field span-2">
                <label for="permiso-descripcion">Descripción</label>
                <textarea id="permiso-descripcion" name="descripcion" rows="3" maxlength="500">{{ old('descripcion') }}</textarea>
                @error('descripcion')<span class="field-error">{{ $message }}</span>@enderror
            </div>
        </div>
        <div class="form-actions">
            <div><a class="button ghost" href="{{ route('admin.permisos.index') }}">Cancelar</a><button class="button primary" type="submit">Crear permiso</button></div>
        </div>
    </form>
</section>

@endsection
