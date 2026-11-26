@extends('layouts.app')
@section('titulo', 'Nueva solicitud de mantenimiento')
@section('titulo_pagina', 'Nueva solicitud de mantenimiento')
@section('contenido')
<x-page-heading title="Nueva solicitud de mantenimiento" eyebrow="Copropiedad activa" />
<a class="back-link" href="{{ route('mantenimiento.index') }}">Volver a Mantenimiento</a>
<section class="panel maintenance-create"><form class="visual-form" method="POST" action="{{ route('mantenimiento.store') }}">@csrf
<div class="field"><label for="titulo">Título</label><input id="titulo" @error('titulo') aria-invalid="true" aria-describedby="titulo-error" @enderror name="titulo" maxlength="180" value="{{ old('titulo') }}" required><x-field-error field="titulo" /></div>
<div class="field"><label for="descripcion">Descripción</label><textarea id="descripcion" @error('descripcion') aria-invalid="true" aria-describedby="descripcion-error" @enderror name="descripcion" maxlength="5000" required>{{ old('descripcion') }}</textarea><x-field-error field="descripcion" /></div>
<p>La solicitud se registrará en estado pendiente. Un gestor podrá asignar responsable y fecha.</p>
<button class="button primary">Registrar solicitud</button></form></section>
@endsection
