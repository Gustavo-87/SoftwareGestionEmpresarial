@extends(auth()->user()->esAdministradorSistema() ? 'layouts.admin' : 'layouts.app')
@section('titulo', 'Auditoría')
@section('titulo_pagina', 'Auditoría administrativa')
@section('contenido')
<x-page-heading title="Registro de auditoría" eyebrow="Seguridad">
    <x-slot name="description">{{ $ambito }} · Consulta de actuaciones y cambios autorizados.</x-slot>
</x-page-heading>
<section class="panel" aria-label="Filtros de auditoría">
    <form method="GET" class="form-grid audit-filters">
        <div class="field"><label for="registro">Registro</label><select id="registro" name="registro">
            @foreach(['operacion' => 'Operación', 'modelos' => 'Cambios de modelos', 'seguridad' => 'Seguridad de acceso'] as $valor => $etiqueta)
                <option value="{{ $valor }}" @selected($registro === $valor)>{{ $etiqueta }}</option>
            @endforeach
        </select></div>
        <div class="field"><label for="desde">Desde</label><input id="desde" name="desde" type="date" value="{{ $filtros['desde'] ?? '' }}"></div>
        <div class="field"><label for="hasta">Hasta</label><input id="hasta" name="hasta" type="date" value="{{ $filtros['hasta'] ?? '' }}"></div>
        <div class="field"><label for="actor">Usuario</label><select id="actor" name="actor">
            <option value="">Todos</option>
            @foreach($actores as $actor)<option value="{{ $actor->id }}" @selected((string) ($filtros['actor'] ?? '') === (string) $actor->id)>{{ $actor->name }}</option>@endforeach
        </select></div>
        <div class="field"><label for="canal">Canal de acceso</label><select id="canal" name="canal">
            <option value="">Todos</option><option value="web" @selected(($filtros['canal'] ?? '') === 'web')>Web</option><option value="api" @selected(($filtros['canal'] ?? '') === 'api')>API</option>
        </select></div>
        <div class="heading-actions"><button class="button primary">Filtrar</button><a class="button subtle" href="{{ url()->current() }}">Limpiar</a></div>
        @if($errors->any())<x-notice variant="error">{{ $errors->first() }}</x-notice>@endif
    </form>
</section>
@if($registro === 'seguridad' && !$plataforma)
    <p class="section-help">Los intentos sin contexto operativo confirmado se reservan a la administración de plataforma.</p>
@endif
<section class="panel audit-panel">
    <div class="audit-header">
        <div class="audit-col-action">Acción</div><div class="audit-col-module">Área</div>
        <div class="audit-col-user">Usuario</div><div class="audit-col-date">Fecha y hora</div>
        <div class="audit-col-relative">Antigüedad</div>
    </div>
    <div class="audit-rows">
        @forelse($logs as $log)
            <article class="audit-row">
                <div class="audit-col-action"><strong>{{ $log->human_action }}</strong>
                    @if($registro === 'modelos')
                        <details><summary>Ver cambios</summary>
                            @foreach($log->detalleCambios() as $cambio)
                                <p><b>{{ $cambio['campo'] }}</b>: {{ $cambio['antes'] }} → {{ $cambio['despues'] }}</p>
                            @endforeach
                        </details>
                    @elseif($registro === 'seguridad')
                        <small>{{ strtoupper($log->metadata['canal'] ?? '') }} · IP {{ $log->ip_address ?? 'No registrada' }}</small>
                    @endif
                </div>
                <div class="audit-col-module"><span class="audit-module-badge">{{ $log->module }}</span></div>
                <div class="audit-col-user">{{ $log->user?->name ?? 'Sin sesión autenticada' }}</div>
                <div class="audit-col-date"><time datetime="{{ $log->created_at->toIso8601String() }}">{{ $log->created_at->format('d/m/Y H:i') }}</time></div>
                <div class="audit-col-relative">{{ $log->created_at->locale('es')->diffForHumans() }}</div>
            </article>
        @empty
            <div class="empty-state"><h3>Sin registros para esta consulta</h3><p>Ajusta los filtros o vuelve a consultar después de una actuación.</p></div>
        @endforelse
    </div>
    <div class="panel-footer">{{ $logs->links() }}</div>
</section>
@endsection
