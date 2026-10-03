@extends('layouts.admin')

@section('titulo', 'Administración del sistema')
@section('titulo_pagina', 'Resumen de plataforma')

@section('contenido')
<div class="admin-home">
<x-page-heading
    title="Resumen de plataforma"
    eyebrow="Administración del sistema"
>
    <x-slot name="actions">
        <a href="{{ route('panel') }}" class="button subtle">Ir al panel operativo →</a>
    </x-slot>
</x-page-heading>

{{-- Métricas globales --}}
<div class="metrics admin-home-cards">
    <x-metric-card
        label="Organizaciones"
        :value="number_format($totalOrganizaciones)"
        note="{{ $organizacionesActivas }} activas"
        variant="info"
        :href="route('admin.organizaciones.index')"
    >
        <x-slot name="icon"><svg viewBox="0 0 24 24" focusable="false"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 21v-4h6v4M9 7h1m4 0h1M9 11h1m4 0h1"/></svg></x-slot>
        <div class="admin-home-card-footer"><p>Crear, editar y administrar organizaciones.</p><span>Ver organizaciones <span aria-hidden="true">→</span></span></div>
    </x-metric-card>
    <x-metric-card
        label="Copropiedades"
        :value="number_format($totalCopropiedades)"
        note="{{ $copropiedadesActivas }} activas"
        variant="cyan"
        :href="route('admin.copropiedades.index')"
    >
        <x-slot name="icon"><svg viewBox="0 0 24 24" focusable="false"><path d="m3 10 9-7 9 7M5 9v12h14V9M9 21v-7h6v7"/></svg></x-slot>
        <div class="admin-home-card-footer"><p>Administrar copropiedades y su configuración.</p><span>Ver copropiedades <span aria-hidden="true">→</span></span></div>
    </x-metric-card>
    <x-metric-card
        label="Membresías"
        :value="number_format($totalMembresias)"
        note="{{ $membresiasActivas }} activas · {{ $membresiasSuspendidas }} suspendida{{ $membresiasSuspendidas !== 1 ? 's' : '' }}"
        variant="success"
        :href="route('admin.membresias.index')"
    >
        <x-slot name="icon"><svg viewBox="0 0 24 24" focusable="false"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M5.5 17c0-4 7-4 7 0M16 9h2m-2 4h2"/></svg></x-slot>
        <div class="admin-home-card-footer"><p>Vincular usuarios y gestionar sus roles.</p><span>Ver membresías <span aria-hidden="true">→</span></span></div>
    </x-metric-card>
    <x-metric-card
        label="Usuarios globales"
        :value="number_format($totalUsuarios)"
        note="+{{ $usuariosNuevosSemana }} esta semana"
        variant="accent"
        :href="route('admin.usuarios-globales.index')"
    >
        <x-slot name="icon"><svg viewBox="0 0 24 24" focusable="false"><circle cx="9" cy="8" r="3"/><path d="M3 21v-2a6 6 0 0 1 12 0v2M16 5a3 3 0 0 1 0 6m2 4a5 5 0 0 1 3 4v2"/></svg></x-slot>
        <div class="admin-home-card-footer"><p>Administrar las cuentas de la plataforma.</p><span>Ver usuarios <span aria-hidden="true">→</span></span></div>
    </x-metric-card>
</div>

{{-- Visualizaciones --}}
<div class="admin-charts-grid">
    {{-- Resumen de membresías --}}
    <div class="panel admin-chart-panel">
        <div class="panel-header">
            <h2>Estado de membresías</h2>
        </div>
        <div class="admin-membership-summary">
            @if($totalMembresias > 0)
                @foreach([
                    ['etiqueta' => 'Activas', 'cantidad' => $membresiasActivas, 'clase' => 'active'],
                    ['etiqueta' => 'Suspendidas', 'cantidad' => $membresiasSuspendidas, 'clase' => 'suspended'],
                    ['etiqueta' => 'Finalizadas', 'cantidad' => $membresiasFinalizadas, 'clase' => 'ended'],
                ] as $estado)
                    <div class="admin-membership-row {{ $estado['clase'] }}">
                        <span class="admin-membership-label"><i aria-hidden="true"></i>{{ $estado['etiqueta'] }}</span>
                        <strong>{{ $estado['cantidad'] }}</strong>
                        <span class="admin-membership-percent">{{ round(($estado['cantidad'] / $totalMembresias) * 100) }}%</span>
                    </div>
                @endforeach
                <p class="admin-membership-total">{{ $totalMembresias }} membresías en total</p>
            @else
                <x-empty-state title="Sin membresías registradas" description="Aquí verás su distribución por estado." />
            @endif
        </div>
    </div>

    {{-- Barras: Copropiedades por organización --}}
    <div class="panel admin-chart-panel">
        <div class="panel-header">
            <h2>Copropiedades por organización</h2>
        </div>
        <div class="admin-bars-container">
            @forelse($copropiedadesPorOrganizacion as $org)
                <div class="admin-bar-row">
                    <div class="admin-bar-label">{{ $org['nombre'] }}</div>
                    <div class="admin-bar-track" aria-hidden="true">
                        <div class="admin-bar-fill" style="width:{{ $maxCopropiedades > 0 ? round(($org['cantidad'] / $maxCopropiedades) * 100) : 0 }}%"></div>
                    </div>
                    <div class="admin-bar-value">{{ $org['cantidad'] }}</div>
                </div>
            @empty
                <x-empty-state title="Sin organizaciones registradas" description="Aquí verás las copropiedades de cada organización." />
            @endforelse
        </div>
    </div>
</div>

</div>
@endsection
