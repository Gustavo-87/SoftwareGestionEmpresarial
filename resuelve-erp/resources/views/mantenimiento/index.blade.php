@extends('layouts.app')
@section('titulo', 'Mantenimiento')
@section('titulo_pagina', 'Mantenimiento')
@section('contenido')
<x-page-heading title="Mantenimiento" eyebrow="Gestión de mantenimiento">
<x-slot name="actions">@can('create', App\Models\Mantenimiento::class)<a class="button primary" href="{{ route('mantenimiento.create') }}">Nueva solicitud</a>@endcan</x-slot>
</x-page-heading>
<p>Solicitudes autorizadas de la Copropiedad activa.</p>
<section class="panel"><div class="table-wrap"><table><thead><tr><th>Solicitud</th><th>Solicitante</th><th>Responsable</th><th>Fecha programada</th><th>Estado</th></tr></thead><tbody>
@forelse($mantenimientos as $mantenimiento)
<tr><td><a href="{{ route('mantenimiento.show', $mantenimiento) }}">{{ $mantenimiento->titulo }}</a></td><td>{{ $mantenimiento->solicitante->name }}</td><td>{{ $mantenimiento->responsable?->name ?? 'Sin asignar' }}</td><td>{{ $mantenimiento->fecha_programada?->format('d/m/Y') ?? 'Sin programar' }}</td><td>{{ $mantenimiento->etiquetaEstado() }}</td></tr>
@empty
<tr><td colspan="5"><x-empty-state title="No hay solicitudes de mantenimiento" description="Las solicitudes autorizadas aparecerán aquí." /></td></tr>
@endforelse
</tbody></table></div>{{ $mantenimientos->links() }}</section>
@endsection
