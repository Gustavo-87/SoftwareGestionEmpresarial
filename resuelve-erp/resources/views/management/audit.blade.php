@extends(auth()->user()->esAdministradorSistema() ? 'layouts.admin' : 'layouts.app')
@section('titulo', 'Auditoría')
@section('titulo_pagina', 'Auditoría administrativa')

@section('contenido')
<x-page-heading
    title="Registro de auditoría"
    eyebrow="Seguridad"
/>

<section class="panel audit-panel">
    {{-- Encabezados de columna (solo desktop) --}}
    <div class="audit-header">
        <div class="audit-col-audit-col-action">Acción</div>
        <div class="audit-col-module">Área</div>
        <div class="audit-col-user">Usuario</div>
        <div class="audit-col-date">Fecha y hora</div>
        <div class="audit-col-relative">Antigüedad</div>
    </div>

    {{-- Registros --}}
    <div class="audit-rows">
        @forelse($logs as $log)
            <div class="audit-row">
                <div class="audit-col-action">
                    <strong>{{ $log->human_action }}</strong>
                </div>
                <div class="audit-col-module">
                    <span class="audit-module-badge">{{ $log->module }}</span>
                </div>
                <div class="audit-col-user">
                    {{ $log->user?->name ?? 'Sistema' }}
                </div>
                <div class="audit-col-date">
                    <time datetime="{{ $log->created_at->toIso8601String() }}">{{ $log->created_at->format('d/m/Y H:i') }}</time>
                </div>
                <div class="audit-col-relative">
                    {{ $log->created_at->locale('es')->diffForHumans() }}
                </div>
            </div>
        @empty
            <div class="empty-state">
                <h3>Sin registros todavía</h3>
                <p>Las acciones que modifiquen información aparecerán aquí.</p>
            </div>
        @endforelse
    </div>

    <div class="panel-footer">{{ $logs->links() }}</div>
</section>
@endsection
