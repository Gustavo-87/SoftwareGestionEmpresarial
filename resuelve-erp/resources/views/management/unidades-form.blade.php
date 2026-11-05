@extends('layouts.app')
@section('titulo', $unidad ? 'Editar unidad' : 'Nueva unidad')
@section('titulo_pagina', $unidad ? 'Editar unidad privada' : 'Nueva unidad privada')
@section('contenido')
<x-page-heading :title="$unidad ? 'Editar unidad privada' : 'Nueva unidad privada'" eyebrow="Residentes y unidades" compact>
    <x-slot name="actions"><a class="button secondary" href="{{ route('management.unidades.index') }}">Volver a unidades</a></x-slot>
</x-page-heading>
<section class="form-panel">
    <div class="form-intro"><div class="form-step">{{ $unidad ? 'E' : '+' }}</div><div><h2>{{ $unidad ? 'Datos de la unidad' : 'Registro de unidad' }}</h2><p>El código identifica la unidad dentro de la Copropiedad activa. El contexto se asigna en backend.</p></div></div>
    <form method="POST" action="{{ $unidad ? route('management.unidades.update', $unidad) : route('management.unidades.store') }}">
        @csrf
        @if($unidad)@method('PUT')@endif
        <div class="form-grid">
            <div class="field">
                <label for="codigo">Código</label>
                <input id="codigo" name="codigo" value="{{ old('codigo', $unidad->codigo ?? '') }}" required maxlength="50" placeholder="A-101">
                <small class="field-help">Obligatorio y único dentro de la Copropiedad activa.</small>
                @error('codigo')<small class="field-error">{{ $message }}</small>@enderror
            </div>
            <div class="field">
                <label for="numero_nombre">Nombre / Número</label>
                <input id="numero_nombre" name="numero_nombre" value="{{ old('numero_nombre', $unidad->numero_nombre ?? '') }}" maxlength="100" placeholder="Apartamento 101">
                @error('numero_nombre')<small class="field-error">{{ $message }}</small>@enderror
            </div>
            <div class="field">
                <label for="torre_bloque">Torre / Bloque</label>
                <input id="torre_bloque" name="torre_bloque" value="{{ old('torre_bloque', $unidad->torre_bloque ?? '') }}" maxlength="50" placeholder="Torre A">
                @error('torre_bloque')<small class="field-error">{{ $message }}</small>@enderror
            </div>
            <div class="field">
                <label for="tipo">Tipo de unidad</label>
                <select id="tipo" name="tipo">
                    <option value="">Sin tipo</option>
                    @foreach($tipos as $tipo)
                        <option value="{{ $tipo->value }}" @selected(old('tipo', $unidad?->tipo?->value) === $tipo->value)>{{ ucfirst($tipo->value) }}</option>
                    @endforeach
                </select>
                @error('tipo')<small class="field-error">{{ $message }}</small>@enderror
            </div>
        </div>
        <div class="form-actions"><div><a class="button subtle" href="{{ route('management.unidades.index') }}">Cancelar</a><button class="button primary">{{ $unidad ? 'Guardar cambios' : 'Crear unidad' }}</button></div></div>
    </form>
</section>
@endsection
