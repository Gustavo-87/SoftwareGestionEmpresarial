@extends('layouts.app')

@section('titulo', 'Panel')
@section('titulo_pagina', 'Panel')

@section('contenido')
    <x-page-heading
        title="Prioriza lo que requiere atención"
        eyebrow="Consola operativa"
    >
        <x-slot name="actions">
            @if($puedeCrearPqrs)<a href="{{ route('pqrs.create') }}" class="button primary"><span>＋</span> Radicar PQRS</a>@endif
        </x-slot>
    </x-page-heading>

    @if($resumen)
        <section class="panel-overview" aria-label="Panorama general">
            <x-metric-card
                label="Vencidas"
                :value="$resumen['vencidas']"
                :note="$resumen['vencidas'] ? 'Plazo superado. Requieren atención inmediata.' : 'No hay PQRS con plazo superado.'"
                variant="danger"
                :href="route('pqrs.index', ['estado' => 'vencidas'])"
                class="overview-vencidas"
            />
            <x-metric-card
                label="Próximas a vencer"
                :value="$resumen['por_vencer']"
                :note="$resumen['por_vencer'] ? 'Vencen dentro de los próximos 3 días.' : 'No hay PQRS próximas a vencer.'"
                variant="warning"
                :href="route('pqrs.index', ['estado' => 'por_vencer'])"
                class="overview-por-vencer"
            />
            <x-metric-card
                label="Pendientes"
                :value="$resumen['pendientes']"
                :note="$resumen['pendientes'] ? 'Requieren gestión o seguimiento.' : 'No hay PQRS pendientes de gestión.'"
                variant="info"
                :href="route('pqrs.index', ['estado' => 'pendientes'])"
                class="overview-pendientes"
            />
            <x-metric-card
                label="Total PQRS autorizadas"
                :value="$resumen['total']"
                :note="$resumen['total'] ? 'Consulta el listado disponible en este contexto.' : 'No hay PQRS para consultar en este contexto.'"
                variant="neutral"
                :href="route('pqrs.index')"
                class="overview-total-general"
            />
        </section>

        {{-- Estadísticas --}}
        @if($resumen['total'] > 0)
            <div class="stats-grid">
                {{-- Donut: Distribución por estado --}}
                <div class="panel stats-panel">
                    <div class="panel-header">
                        <h2>Distribución por estado</h2>
                    </div>
                    <div class="stats-donut-container">
                        @php
                            $gradosAcumulados = 0;
                        @endphp
                        <div class="stats-donut" style="background:conic-gradient(
                            @foreach($estadisticasEstados as $i => $estado)
                                {{ $estado['color'] }} {{ $gradosAcumulados }}deg {{ $gradosAcumulados + ($estado['porcentaje'] * 3.6) }}deg{{ $i < count($estadisticasEstados) - 1 ? ',' : '' }}
                                @php $gradosAcumulados += $estado['porcentaje'] * 3.6; @endphp
                            @endforeach
                        )">
                            <div class="stats-donut-center">
                                <strong>{{ $resumen['total'] }}</strong>
                                <small>total</small>
                            </div>
                        </div>
                        <div class="stats-legend">
                            @foreach($estadisticasEstados as $estado)
                                <div class="stats-legend-item">
                                    <span class="stats-legend-dot" style="background:{{ $estado['color'] }}"></span>
                                    <span class="stats-legend-label">{{ $estado['label'] }}</span>
                                    <span class="stats-legend-value">{{ $estado['cantidad'] }}</span>
                                    <span class="stats-legend-pct">{{ $estado['porcentaje'] }}%</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Barras: PQRS por tipo --}}
                <div class="panel stats-panel">
                    <div class="panel-header">
                        <h2>PQRS por tipo</h2>
                    </div>
                    <div class="stats-bars-container">
                        @forelse($estadisticasTipos as $tipo)
                            <div class="stats-bar-row">
                                <div class="stats-bar-label">{{ Str::limit($tipo['nombre'], 20) }}</div>
                                <div class="stats-bar-track">
                                    <div class="stats-bar-fill" style="width:{{ $tipo['porcentaje'] }}%"></div>
                                </div>
                                <div class="stats-bar-value">{{ $tipo['cantidad'] }}</div>
                            </div>
                        @empty
                            <div class="empty-state" style="padding:20px">
                                <p>Sin tipos registrados</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif

        <section class="panel console-access">
            <div class="panel-header"><div><h2>Accesos disponibles</h2><p>Capacidades autorizadas para la Copropiedad activa.</p></div></div>
            <div class="console-access-grid">
                @if($puedeConsultarDocumentos)<a class="console-access-card" href="{{ route('documentos.index') }}"><span class="metric-icon mint">▤</span><div><h3>Documentos</h3><p>Consulta documentos autorizados.</p></div><span aria-hidden="true">→</span></a>@endif
                @if($notificacionesNoLeidas !== null)<a class="console-access-card" href="{{ route('notifications.index') }}"><span class="metric-icon blue">✓</span><div><h3>Notificaciones</h3><p>{{ $notificacionesNoLeidas ? 'Tienes avisos sin leer.' : 'No tienes avisos sin leer.' }}</p></div><span aria-hidden="true">→</span></a>@endif
                @if($navegacion['carga'] ?? false)<a class="console-access-card" href="{{ route('management.workload') }}"><span class="metric-icon amber">◷</span><div><h3>Carga del equipo</h3><p>Consulta la distribución operativa disponible.</p></div><span aria-hidden="true">→</span></a>@endif
                @if(! $puedeConsultarDocumentos && $notificacionesNoLeidas === null)<x-empty-state title="No hay accesos adicionales disponibles" description="Las capacidades se muestran según tus permisos contextuales." />@endif
            </div>
        </section>
    @else
        <section class="panel"><x-empty-state title="No tienes acceso a PQRS" description="Tu acceso actual no permite consultar solicitudes en esta Copropiedad." /></section>
    @endif
@endsection
