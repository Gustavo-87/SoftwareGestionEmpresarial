@extends('layouts.app')
@section('titulo', 'Editar solicitud')
@section('titulo_pagina', 'Editar solicitud')
@section('contenido')
    <section class="page-heading compact"><div><a class="back-link" href="{{ route('pqrs.index') }}">← Volver al panel</a><span class="eyebrow">PQR-{{ str_pad($pqr->id, 4, '0', STR_PAD_LEFT) }}</span><h1>Actualizar solicitud</h1><p>Modifica la información y registra el avance de la gestión.</p></div></section>
    @include('pqrs.partials.form', ['action' => route('pqrs.update', $pqr), 'method' => 'PUT', 'submitLabel' => 'Guardar cambios'])
@endsection
