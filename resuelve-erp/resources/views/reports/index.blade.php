@extends('layouts.app')

@section('titulo', 'Centro de Reportes')
@section('titulo_pagina', 'Centro de Reportes')

@section('contenido')
    <x-page-heading
        title="Centro de Reportes"
        :eyebrow="$copropiedad->nombre"
        description="Indicadores de la copropiedad activa y descarga de los informes autorizados."
    />

    <section class="panel" aria-labelledby="reportes-pqrs">
        <div class="panel-header">
            <div>
                <h2 id="reportes-pqrs">PQRS</h2>
                <p>Periodo de los registros: {{ $period }}</p>
            </div>
        </div>
        @if($stats['total'] === 0)
            <p>No hay PQRS para los filtros aplicados.</p>
        @else
            <div class="metrics metric-cards">
                <x-metric-card label="Total" :value="number_format($stats['total'])" note="PQRS del periodo" variant="neutral">
                    <x-slot name="icon"><svg viewBox="0 0 24 24" focusable="false"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg></x-slot>
                </x-metric-card>
                <x-metric-card label="Pendientes" :value="number_format($stats['pending'])" note="por responder" variant="warning">
                    <x-slot name="icon"><svg viewBox="0 0 24 24" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></x-slot>
                </x-metric-card>
                <x-metric-card label="Resueltas" :value="number_format($stats['resolved'])" note="respondidas o cerradas" variant="success">
                    <x-slot name="icon"><svg viewBox="0 0 24 24" focusable="false"><path d="m8 12 3 3 5-6"/><circle cx="12" cy="12" r="9"/></svg></x-slot>
                </x-metric-card>
            </div>
        @endif
        <div class="heading-actions">
            <a href="{{ route('reports.pdf', request()->query()) }}" class="button primary">Descargar PDF</a>
            <a href="{{ route('reports.xlsx', request()->query()) }}" class="button subtle">Descargar Excel</a>
            <a href="{{ route('reports.csv', request()->query()) }}" class="button subtle">Descargar CSV</a>
        </div>
    </section>

    <section class="panel" aria-labelledby="reportes-mantenimiento">
        <div class="panel-header">
            <div>
                <h2 id="reportes-mantenimiento">Mantenimiento</h2>
                <p>Solicitudes de mantenimiento de la copropiedad activa.</p>
            </div>
        </div>
        @if($mantenimiento === null)
            <p>No tienes permisos para consultar los indicadores de este módulo.</p>
        @else
            <form method="GET" action="{{ route('reports.index') }}" class="filters inbox-filters report-filters" aria-label="Filtros del reporte de mantenimiento">
                <div class="filter-fields">
                    <label>Estado<select name="mantenimiento_estado"><option value="">Todos los estados</option><option value="pendiente" @selected(request('mantenimiento_estado') === 'pendiente')>Pendiente</option><option value="en_proceso" @selected(request('mantenimiento_estado') === 'en_proceso')>En proceso</option><option value="finalizado" @selected(request('mantenimiento_estado') === 'finalizado')>Finalizado</option></select></label>
                    <label>Responsable<select name="mantenimiento_responsable_id"><option value="">Todos los responsables</option>@foreach($responsables as $responsable)<option value="{{ $responsable->id }}" @selected(request('mantenimiento_responsable_id') == $responsable->id)>{{ $responsable->name }}</option>@endforeach</select></label>
                    <label>Desde<input type="date" name="mantenimiento_desde" value="{{ request('mantenimiento_desde') }}"></label>
                    <label>Hasta<input type="date" name="mantenimiento_hasta" value="{{ request('mantenimiento_hasta') }}"></label>
                </div>
                @foreach(request()->except(['mantenimiento_desde', 'mantenimiento_hasta', 'mantenimiento_estado', 'mantenimiento_responsable_id']) as $clave => $valor)
                    @if(is_scalar($valor))<input type="hidden" name="{{ $clave }}" value="{{ $valor }}">@endif
                @endforeach
                <div class="filter-actions"><button class="button subtle" type="submit">Aplicar filtros</button>@if(request()->hasAny(['mantenimiento_desde', 'mantenimiento_hasta', 'mantenimiento_estado', 'mantenimiento_responsable_id']))<a class="clear-filter" href="{{ route('reports.index', request()->except(['mantenimiento_desde', 'mantenimiento_hasta', 'mantenimiento_estado', 'mantenimiento_responsable_id'])) }}">Restablecer</a>@endif</div>
            </form>
            @if($mantenimiento['total'] === 0)
                <p>{{ request()->hasAny(['mantenimiento_desde', 'mantenimiento_hasta', 'mantenimiento_estado', 'mantenimiento_responsable_id']) ? 'No hay solicitudes de mantenimiento para los filtros aplicados.' : 'No hay solicitudes de mantenimiento registradas.' }}</p>
            @else
                <div class="metrics metric-cards">
                    <x-metric-card label="Total" :value="number_format($mantenimiento['total'])" note="solicitudes del contexto" variant="neutral" />
                    <x-metric-card label="Pendientes" :value="number_format($mantenimiento['pendientes'])" note="por atender" variant="warning" />
                    <x-metric-card label="En proceso" :value="number_format($mantenimiento['en_proceso'])" note="en ejecución" variant="info" />
                    <x-metric-card label="Finalizados" :value="number_format($mantenimiento['finalizados'])" note="cerradas" variant="success" />
                    <x-metric-card label="Atrasados" :value="number_format($mantenimiento['atrasados'])" note="fecha pasada, sin finalizar" variant="danger" />
                </div>
            @endif
            <div class="heading-actions">
                <a href="{{ route('reports.mantenimiento.pdf', $mantenimientoFiltros) }}" class="button primary">Descargar PDF</a>
            </div>
        @endif
    </section>

    <section class="panel" aria-labelledby="reportes-documentos">
        <div class="panel-header">
            <div>
                <h2 id="reportes-documentos">Documentos</h2>
                <p>Documentos de la copropiedad activa.</p>
            </div>
        </div>
        @if($documentos === null)
            <p>No tienes permisos para consultar los indicadores de este módulo.</p>
        @else
            <form method="GET" action="{{ route('reports.index') }}" class="filters inbox-filters report-filters" aria-label="Filtros del reporte de documentos">
                <div class="filter-fields">
                    <label>Estado<select name="documentos_estado"><option value="">Todos los estados</option><option value="activo" @selected(request('documentos_estado') === 'activo')>Activo</option><option value="archivado" @selected(request('documentos_estado') === 'archivado')>Archivado</option></select></label>
                    <label>Desde<input type="date" name="documentos_desde" value="{{ request('documentos_desde') }}"></label>
                    <label>Hasta<input type="date" name="documentos_hasta" value="{{ request('documentos_hasta') }}"></label>
                </div>
                @foreach(request()->except(['documentos_desde', 'documentos_hasta', 'documentos_estado']) as $clave => $valor)
                    @if(is_scalar($valor))<input type="hidden" name="{{ $clave }}" value="{{ $valor }}">@endif
                @endforeach
                <div class="filter-actions"><button class="button subtle" type="submit">Aplicar filtros</button>@if(request()->hasAny(['documentos_desde', 'documentos_hasta', 'documentos_estado']))<a class="clear-filter" href="{{ route('reports.index', request()->except(['documentos_desde', 'documentos_hasta', 'documentos_estado'])) }}">Restablecer</a>@endif</div>
            </form>
            @if($documentos['total'] === 0)
                <p>{{ request()->hasAny(['documentos_desde', 'documentos_hasta', 'documentos_estado']) ? 'No hay documentos para los filtros aplicados.' : 'No hay documentos registrados.' }}</p>
            @else
                <div class="metrics metric-cards">
                    <x-metric-card label="Total" :value="number_format($documentos['total'])" note="documentos del contexto" variant="neutral">
                        <x-slot name="icon"><svg viewBox="0 0 24 24" focusable="false"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 7h6M9 11h6M9 15h4"/></svg></x-slot>
                    </x-metric-card>
                    <x-metric-card label="Activos" :value="number_format($documentos['activos'])" note="vigentes" variant="success">
                        <x-slot name="icon"><svg viewBox="0 0 24 24" focusable="false"><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/></svg></x-slot>
                    </x-metric-card>
                    <x-metric-card label="Archivados" :value="number_format($documentos['archivados'])" note="fuera de vigencia" variant="neutral">
                        <x-slot name="icon"><svg viewBox="0 0 24 24" focusable="false"><rect x="3" y="4" width="18" height="5" rx="1"/><path d="M5 9v10h14V9M10 13h4"/></svg></x-slot>
                    </x-metric-card>
                </div>
            @endif
            <div class="heading-actions">
                <a href="{{ route('reports.documentos.pdf', $documentosFiltros) }}" class="button primary">Descargar PDF</a>
            </div>
        @endif
    </section>

    <section class="panel" aria-labelledby="reportes-consolidado">
        <div class="panel-header">
            <div>
                <h2 id="reportes-consolidado">Consolidado General</h2>
                <p>Registros bajo gestión de la copropiedad activa en los módulos autorizados.</p>
            </div>
        </div>
        <div class="metrics metric-cards">
            <x-metric-card label="Registros bajo gestión" :value="number_format($consolidado['total'])" note="módulos autorizados" variant="accent">
                <x-slot name="icon"><svg viewBox="0 0 24 24" focusable="false"><path d="M4 19V9M10 19V5M16 19v-7M22 19H2"/></svg></x-slot>
            </x-metric-card>
            @foreach($consolidado['modulos'] as $modulo)
                <x-metric-card label="{{ $modulo['nombre'] }}" :value="number_format($modulo['total'])" note="registros del módulo" variant="info" />
            @endforeach
        </div>
    </section>
@endsection
