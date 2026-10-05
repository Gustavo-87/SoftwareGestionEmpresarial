@extends('layouts.app')
@section('titulo', 'Mantenimiento')
@section('titulo_pagina', 'Mantenimiento')
@section('contenido')
<x-page-heading title="Mantenimiento" eyebrow="Gestión de mantenimiento">
<x-slot name="actions">@can('create', App\Models\Mantenimiento::class)<a class="button primary" href="{{ route('mantenimiento.create') }}">Nueva solicitud</a>@endcan</x-slot>
</x-page-heading>
<p>Solicitudes autorizadas de la Copropiedad activa.</p>
<section class="panel-overview" aria-label="Resumen de mantenimientos">
    <x-metric-card label="Total" :value="number_format($resumen['total'])" note="solicitudes del contexto" variant="neutral">
        <x-slot name="icon"><svg viewBox="0 0 24 24" focusable="false"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg></x-slot>
    </x-metric-card>
    <x-metric-card label="Pendientes" :value="number_format($resumen['pendientes'])" note="por atender" variant="warning">
        <x-slot name="icon"><svg viewBox="0 0 24 24" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></x-slot>
    </x-metric-card>
    <x-metric-card label="En proceso" :value="number_format($resumen['en_proceso'])" note="en ejecución" variant="info">
        <x-slot name="icon"><svg viewBox="0 0 24 24" focusable="false"><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 3v6h-6"/></svg></x-slot>
    </x-metric-card>
    <x-metric-card label="Finalizados" :value="number_format($resumen['finalizados'])" note="cerradas" variant="success">
        <x-slot name="icon"><svg viewBox="0 0 24 24" focusable="false"><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/></svg></x-slot>
    </x-metric-card>
    <x-metric-card label="Programados" :value="number_format($resumen['programados'])" note="con fecha programada" variant="cyan">
        <x-slot name="icon"><svg viewBox="0 0 24 24" focusable="false"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/></svg></x-slot>
    </x-metric-card>
    <x-metric-card label="Atrasados" :value="number_format($resumen['atrasados'])" note="fecha pasada, sin finalizar" variant="danger">
        <x-slot name="icon"><svg viewBox="0 0 24 24" focusable="false"><path d="M12 3 2 20h20L12 3z"/><path d="M12 10v4M12 17h.01"/></svg></x-slot>
    </x-metric-card>
</section>
<section class="panel"><div class="table-wrap"><table><thead><tr><th>Solicitud</th><th>Solicitante</th><th>Responsable</th><th>Fecha programada</th><th>Estado</th></tr></thead><tbody>
@forelse($mantenimientos as $mantenimiento)
<tr><td><a href="{{ route('mantenimiento.show', $mantenimiento) }}">{{ $mantenimiento->titulo }}</a></td><td>{{ $mantenimiento->solicitante->name }}</td><td>{{ $mantenimiento->responsable?->name ?? 'Sin asignar' }}</td><td>{{ $mantenimiento->fecha_programada?->format('d/m/Y') ?? 'Sin programar' }}</td><td>{{ $mantenimiento->etiquetaEstado() }}</td></tr>
@empty
<tr><td colspan="5"><x-empty-state title="No hay solicitudes de mantenimiento" description="Las solicitudes autorizadas aparecerán aquí." /></td></tr>
@endforelse
</tbody></table></div>{{ $mantenimientos->links() }}</section>
@endsection
