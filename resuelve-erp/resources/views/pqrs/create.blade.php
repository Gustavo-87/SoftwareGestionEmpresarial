@extends('layouts.app')
@section('titulo', 'Nueva solicitud')
@section('titulo_pagina', 'Nueva solicitud')
@section('contenido')
    <section class="page-heading compact pqr-create-heading" aria-labelledby="pqr-create-title">
        <div>
            <a class="back-link" href="{{ route('pqrs.index') }}">← Volver a solicitudes</a>
            <span class="eyebrow">Nueva PQRS</span>
            <h1 id="pqr-create-title">Radicar una nueva PQRS</h1>
            <p>Describe tu caso, adjunta soportes si los necesitas y radica la solicitud para iniciar su seguimiento.</p>
        </div>
    </section>

    @include('pqrs.partials.create-form', ['tipos' => $tipos])
@endsection
