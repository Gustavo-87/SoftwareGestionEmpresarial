@extends('layouts.app')

@section('titulo', 'Panel')
@section('titulo_pagina', 'Panel')
@section('clase_pagina', 'panel-page')

@section('contenido')
    {{-- Encabezado de una fila: Copropiedad activa y acción principal. --}}
    <section class="panel-heading">
        <h1>{{ $copropiedad->nombre }}</h1>
        <div class="heading-actions">
            @if($puedeCrearPqrs)<a href="{{ route('pqrs.create') }}" class="button primary"><span>＋</span> Radicar PQRS</a>@endif
        </div>
    </section>

    @if($resumen)
        {{-- Prioridades: etiqueta y cifra, sin explicaciones repetidas. --}}
        <section class="atencion-grid" aria-label="Atención prioritaria">
            <x-metric-card
                label="PQRS vencidas"
                :value="$resumen['vencidas']"
                variant="danger"
                :href="route('pqrs.index', ['estado' => 'vencidas'])"
            >
                <x-slot name="icon"><svg viewBox="0 0 24 24" focusable="false"><path d="M12 3 2 20h20L12 3z"/><path d="M12 10v4M12 17h.01"/></svg></x-slot>
            </x-metric-card>
            <x-metric-card
                label="Próximas a vencer"
                :value="$resumen['por_vencer']"
                variant="warning"
                :href="route('pqrs.index', ['estado' => 'por_vencer'])"
            >
                <x-slot name="icon"><svg viewBox="0 0 24 24" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></x-slot>
            </x-metric-card>
            <x-metric-card
                label="PQRS pendientes"
                :value="$resumen['pendientes']"
                variant="info"
                :href="route('pqrs.index', ['estado' => 'pendientes'])"
            >
                <x-slot name="icon"><svg viewBox="0 0 24 24" focusable="false"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 7h6M9 11h6M9 15h4"/></svg></x-slot>
            </x-metric-card>
        </section>

        {{-- Módulos: total, distribución por estados y acceso. --}}
        <section class="operativo-grid" aria-label="Resumen operativo por módulo">
            @isset($indicadores['pqrs'])
                <div class="panel stats-panel operativo-pqrs-panel">
                    <div class="panel-header">
                        <div class="dashboard-heading-info"><span class="dashboard-section-icon metric-icon blue">✉</span><h2>PQRS</h2></div>
                        <span class="operativo-total operativo-total-pqrs">{{ $indicadores['pqrs']['total'] }} solicitudes</span>
                    </div>
                    <p class="modulo-integracion">{{ $indicadores['pqrs']['con_mantenimiento'] ?? 0 }} con mantenimiento vinculado</p>
                    <x-modulo-estados :estados="$indicadores['pqrs']['estados']" :total="$indicadores['pqrs']['total']" vacio="Sin solicitudes en este contexto." />
                    <div class="panel-footer"><a class="modulo-ir modulo-ir-pqrs" href="{{ route('pqrs.index') }}">Ir a PQRS <span aria-hidden="true">›</span></a></div>
                </div>
            @endisset
            @isset($indicadores['mantenimiento'])
                <div class="panel stats-panel operativo-mantenimiento-panel">
                    <div class="panel-header">
                        <div class="dashboard-heading-info"><span class="dashboard-section-icon metric-icon mint">⚙</span><h2>Mantenimiento</h2></div>
                        <span class="operativo-total operativo-total-mantenimiento">{{ $indicadores['mantenimiento']['total'] }} registros</span>
                    </div>
                    <p class="modulo-integracion">{{ $indicadores['mantenimiento']['vinculados'] ?? 0 }} vinculados · {{ $indicadores['mantenimiento']['abiertos'] ?? 0 }} abiertos · {{ $indicadores['mantenimiento']['con_evidencia'] ?? 0 }} con evidencia</p>
                    <x-modulo-estados :estados="$indicadores['mantenimiento']['estados']" :total="$indicadores['mantenimiento']['total']" vacio="Sin registros de mantenimiento en este contexto." />
                    <div class="panel-footer"><a class="modulo-ir modulo-ir-mantenimiento" href="{{ route('mantenimiento.index') }}">Ir a Mantenimiento <span aria-hidden="true">›</span></a></div>
                </div>
            @endisset
            @isset($indicadores['documentos'])
                <div class="panel stats-panel operativo-documentos-panel">
                    <div class="panel-header">
                        <div class="dashboard-heading-info"><span class="dashboard-section-icon metric-icon violet">▤</span><h2>Documentos</h2></div>
                        <span class="operativo-total operativo-total-documentos">{{ $indicadores['documentos']['total'] }} documentos</span>
                    </div>
                    <x-modulo-estados :estados="$indicadores['documentos']['estados']" :total="$indicadores['documentos']['total']" vacio="Sin documentos en este contexto." />
                    <div class="panel-footer"><a class="modulo-ir modulo-ir-documentos" href="{{ route('documentos.index') }}">Ir a Documentos <span aria-hidden="true">›</span></a></div>
                </div>
            @endisset
        </section>

        {{-- Top 5 de PQRS por tipo: bloque compacto exigido por la rúbrica. --}}
        <section class="panel stats-panel modulo-pqrs-accent top-tipos">
            <div class="panel-header"><h2>Top 5 de PQRS por tipo</h2></div>
            <div class="stats-bars-container">
                @forelse($estadisticasTipos as $tipo)
                    <div class="stats-bar-row">
                        <div class="stats-bar-label">{{ Str::limit($tipo['nombre'], 20) }}</div>
                        <div class="stats-bar-track"><div class="stats-bar-fill" style="width:{{ $tipo['porcentaje'] }}%"></div></div>
                        <div class="stats-bar-value">{{ $tipo['cantidad'] }}</div>
                    </div>
                @empty
                    <div class="empty-state" style="padding:12px">
                        <p>Sin tipos registrados</p>
                    </div>
                @endforelse
            </div>
        </section>
    @else
        <section class="panel"><x-empty-state title="No tienes acceso a PQRS" description="Tu acceso actual no permite consultar solicitudes en esta Copropiedad." /></section>
    @endif
@endsection
