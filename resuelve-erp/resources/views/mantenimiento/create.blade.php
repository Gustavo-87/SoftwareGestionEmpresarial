@extends('layouts.app')
@section('titulo', 'Nueva solicitud de mantenimiento')
@section('titulo_pagina', 'Nueva solicitud de mantenimiento')
@section('contenido')
<x-page-heading title="Nueva solicitud de mantenimiento" eyebrow="Copropiedad activa" />
<a class="back-link" href="{{ route('mantenimiento.index') }}">Volver a Mantenimiento</a>
<section class="panel maintenance-create"><form method="POST" action="{{ route('mantenimiento.store') }}">@csrf
<div class="field"><label for="titulo">Título</label><input id="titulo" name="titulo" maxlength="180" value="{{ old('titulo') }}" required>@error('titulo')<small class="field-error">{{ $message }}</small>@enderror</div>
<div class="field"><label for="descripcion">Descripción</label><textarea id="descripcion" name="descripcion" maxlength="5000" required>{{ old('descripcion') }}</textarea>@error('descripcion')<small class="field-error">{{ $message }}</small>@enderror</div>
<p>La solicitud se registrará en estado pendiente. Un gestor podrá asignar responsable y fecha.</p>
<button class="button primary">Registrar solicitud</button></form></section>
@endsection
