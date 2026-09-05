@extends('layouts.admin')

@section('titulo', 'Administración del sistema')
@section('titulo_pagina', 'Resumen de plataforma')

@section('contenido')
<x-page-heading
    title="Resumen de plataforma"
    eyebrow="Administración del sistema"
>
    <x-slot name="actions">
        <a href="{{ route('panel') }}" class="button subtle">Panel operativo →</a>
    </x-slot>
</x-page-heading>

{{-- Métricas globales --}}
<div class="metrics">
    <x-metric-card
        label="Organizaciones"
        :value="number_format($totalOrganizaciones)"
        note="{{ $organizacionesActivas }} activas"
        variant="info"
        :href="route('admin.organizaciones.index')"
    />
    <x-metric-card
        label="Copropiedades"
        :value="number_format($totalCopropiedades)"
        note="{{ $copropiedadesActivas }} activas"
        variant="cyan"
        :href="route('admin.copropiedades.index')"
    />
    <x-metric-card
        label="Membresías"
        :value="number_format($totalMembresias)"
        note="{{ $membresiasActivas }} activas · {{ $membresiasSuspendidas }} suspendida{{ $membresiasSuspendidas !== 1 ? 's' : '' }}"
        variant="success"
        :href="route('admin.membresias.index')"
    />
    <x-metric-card
        label="Usuarios globales"
        :value="number_format($totalUsuarios)"
        note="+{{ $usuariosNuevosSemana }} esta semana"
        variant="accent"
        :href="route('admin.usuarios-globales.index')"
    />
</div>

{{-- Visualizaciones --}}
<div class="admin-charts-grid">
    {{-- Donut de membresías --}}
    <div class="panel admin-chart-panel">
        <div class="panel-header">
            <h2>Estado de membresías</h2>
        </div>
        <div class="admin-donut-container">
            @if($totalMembresias > 0)
                @php
                    $pctActivas = round(($membresiasActivas / $totalMembresias) * 100);
                    $pctSuspendidas = round(($membresiasSuspendidas / $totalMembresias) * 100);
                    $pctFinalizadas = round(($membresiasFinalizadas / $totalMembresias) * 100);
                    $degActivas = $pctActivas * 3.6;
                    $degSuspendidas = $pctSuspendidas * 3.6;
                @endphp
                <div class="admin-donut" style="background:conic-gradient(var(--green) 0deg {{ $degActivas }}deg, var(--amber) {{ $degActivas }}deg {{ $degActivas + $degSuspendidas }}deg, var(--coral) {{ $degActivas + $degSuspendidas }}deg 360deg)">
                    <div class="admin-donut-center">
                        <strong>{{ $totalMembresias }}</strong>
                        <small>total</small>
                    </div>
                </div>
                <div class="admin-donut-legend">
                    <div class="admin-legend-item">
                        <span class="admin-legend-dot" style="background:var(--green)"></span>
                        <span class="admin-legend-label">Activas</span>
                        <span class="admin-legend-value">{{ $membresiasActivas }}</span>
                        <span class="admin-legend-pct">{{ $pctActivas }}%</span>
                    </div>
                    <div class="admin-legend-item">
                        <span class="admin-legend-dot" style="background:var(--amber)"></span>
                        <span class="admin-legend-label">Suspendidas</span>
                        <span class="admin-legend-value">{{ $membresiasSuspendidas }}</span>
                        <span class="admin-legend-pct">{{ $pctSuspendidas }}%</span>
                    </div>
                    <div class="admin-legend-item">
                        <span class="admin-legend-dot" style="background:var(--coral)"></span>
                        <span class="admin-legend-label">Finalizadas</span>
                        <span class="admin-legend-value">{{ $membresiasFinalizadas }}</span>
                        <span class="admin-legend-pct">{{ $pctFinalizadas }}%</span>
                    </div>
                </div>
            @else
                <div class="empty-state" style="padding:30px">
                    <p>Sin membresías registradas</p>
                </div>
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
                    <div class="admin-bar-label">{{ Str::limit($org['nombre'], 25) }}</div>
                    <div class="admin-bar-track">
                        <div class="admin-bar-fill" style="width:{{ $maxCopropiedades > 0 ? round(($org['cantidad'] / $maxCopropiedades) * 100) : 0 }}%"></div>
                    </div>
                    <div class="admin-bar-value">{{ $org['cantidad'] }}</div>
                </div>
            @empty
                <div class="empty-state" style="padding:30px">
                    <p>Sin organizaciones registradas</p>
                </div>
            @endforelse
        </div>
    </div>
</div>

{{-- Gestión principal --}}
<div class="panel">
    <div class="panel-header">
        <h2>Gestión principal</h2>
    </div>
    <div class="admin-actions-grid">
        <a href="{{ route('admin.organizaciones.index') }}" class="admin-action-card">
            <strong>Organizaciones</strong>
            <p>Crear, editar, desactivar y reactivar</p>
        </a>
        <a href="{{ route('admin.copropiedades.index') }}" class="admin-action-card">
            <strong>Copropiedades</strong>
            <p>Gestionar copropiedades y configuración</p>
        </a>
        <a href="{{ route('admin.membresias.index') }}" class="admin-action-card">
            <strong>Membresías</strong>
            <p>Asociar usuarios y gestionar roles</p>
        </a>
        <a href="{{ route('admin.usuarios-globales.index') }}" class="admin-action-card">
            <strong>Usuarios globales</strong>
            <p>Administrar cuentas de la plataforma</p>
        </a>
    </div>
</div>
@endsection
