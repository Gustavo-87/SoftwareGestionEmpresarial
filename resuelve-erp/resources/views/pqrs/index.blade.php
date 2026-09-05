@extends('layouts.app')

@section('titulo', 'PQRS')
@section('titulo_pagina', 'PQRS')

@section('contenido')
    @if($errors->any())
        <x-notice variant="error" title="No fue posible aplicar la acción solicitada."><p>Revisa la información e inténtalo de nuevo.</p></x-notice>
    @endif

    <section class="page-heading inbox-heading" aria-labelledby="pqrs-title">
        <div>
            <span class="eyebrow">Bandeja operativa</span>
            <h1 id="pqrs-title">PQRS</h1>
        </div>
        <div class="heading-actions" aria-label="Acciones de PQRS">
            @if($navegacion['exportarInformes'] ?? false)
                <a href="{{ route('reports.xlsx', request()->query()) }}" class="button subtle">Exportar Excel</a>
                <a href="{{ route('reports.pdf', request()->query()) }}" class="button subtle">Exportar PDF</a>
            @endif
            @if($navegacion['crearPqrs'] ?? false)<a href="{{ route('pqrs.create') }}" class="button primary"><span aria-hidden="true">＋</span> Radicar PQRS</a>@endif
        </div>
    </section>

    <div class="metrics" style="margin-bottom:18px">
        <x-metric-card
            label="Vencidas"
            :value="$resumen['vencidas']"
            variant="danger"
            :href="route('pqrs.index', ['estado' => 'vencidas'])"
        />
        <x-metric-card
            label="Próximas a vencer"
            :value="$resumen['por_vencer']"
            variant="warning"
            :href="route('pqrs.index', ['estado' => 'por_vencer'])"
        />
        <x-metric-card
            label="Pendientes"
            :value="$resumen['pendientes']"
            variant="info"
            :href="route('pqrs.index', ['estado' => 'pendientes'])"
        />
        <x-metric-card
            label="Total"
            :value="$resumen['total']"
            variant="neutral"
            :href="route('pqrs.index')"
        />
    </div>

    <section class="panel pqrs-inbox" id="solicitudes" aria-labelledby="solicitudes-title">
        <div class="inbox-toolbar">
            <div>
                <h2 id="solicitudes-title">Solicitudes</h2>
                <p>Usa los filtros existentes para ajustar la bandeja.</p>
            </div>
            <form method="GET" action="{{ route('pqrs.index') }}" class="filters inbox-filters" aria-label="Filtros de PQRS">
                <label class="search-box"><span aria-hidden="true">⌕</span><input type="search" name="buscar" placeholder="Radicado, asunto o residente" value="{{ request('buscar') }}" aria-label="Buscar por radicado, asunto o residente"></label>
                <div class="filter-fields">
                    <label>Estado<select name="estado"><option value="">Todos los estados</option><option value="pendientes" @selected(request('estado') === 'pendientes')>Pendientes</option><option value="por_vencer" @selected(request('estado') === 'por_vencer')>Por vencer</option><option value="radicada" @selected(request('estado') === 'radicada')>Radicada</option><option value="en_revision" @selected(request('estado') === 'en_revision')>En revisión</option><option value="respondida" @selected(request('estado') === 'respondida')>Respondida</option><option value="cerrada" @selected(request('estado') === 'cerrada')>Cerrada</option></select></label>
                    @if($puedeGestionar)<label>Prioridad<select name="prioridad"><option value="">Todas las prioridades</option><option value="alta" @selected(request('prioridad') === 'alta')>Alta</option><option value="media" @selected(request('prioridad') === 'media')>Media</option><option value="baja" @selected(request('prioridad') === 'baja')>Baja</option></select></label>@endif
                    <label>Tipo<select name="tipo_pqr_id"><option value="">Todos los tipos</option>@foreach($tipos as $tipo)<option value="{{ $tipo->id }}" @selected(request('tipo_pqr_id') == $tipo->id)>{{ $tipo->nombre }}</option>@endforeach</select></label>
                    @if($navegacion['verTodasPqrs'] ?? false)<label>Responsable<select name="assigned_to_id"><option value="">Todos los responsables</option>@foreach($gestores as $gestor)<option value="{{ $gestor->id }}" @selected(request('assigned_to_id') == $gestor->id)>{{ $gestor->name }}</option>@endforeach</select></label>@endif
                    <label>Desde<input type="date" name="desde" value="{{ request('desde') }}"></label>
                    <label>Hasta<input type="date" name="hasta" value="{{ request('hasta') }}"></label>
                </div>
                <div class="filter-actions"><button class="button subtle" type="submit">Aplicar filtros</button>@if(request()->hasAny(['buscar', 'estado', 'tipo_pqr_id', 'assigned_to_id', 'prioridad', 'desde', 'hasta']))<a class="clear-filter" href="{{ route('pqrs.index') }}">Restablecer</a>@endif</div>
            </form>
        </div>

        @if(request()->hasAny(['buscar', 'estado', 'tipo_pqr_id', 'assigned_to_id', 'prioridad', 'desde', 'hasta']))
            <div class="active-filter-note" role="status"><span aria-hidden="true">✓</span> Se muestran resultados con filtros activos. <a href="{{ route('pqrs.index') }}">Restablecer la bandeja</a></div>
        @endif

        <div class="mobile-request-list" aria-label="Lista de PQRS">
            @forelse($pqrs as $pqr)
                <article class="request-card">
                    <a class="request-card-link" href="{{ route('pqrs.show', $pqr) }}">
                        <div class="request-card-top"><span class="radicado">PQR-{{ str_pad($pqr->id, 4, '0', STR_PAD_LEFT) }}</span>@can('update', $pqr)<span class="priority-badge prioridad-{{ $pqr->prioridad }}" aria-label="Prioridad {{ $pqr->prioridad_label }}">{{ $pqr->prioridad_label }}</span>@endcan<span class="status {{ $pqr->estado }}"><i aria-hidden="true"></i>{{ $pqr->estado_label }}</span></div>
                        <strong>{{ $pqr->asunto }}</strong>
                        <div class="request-card-details">
                            <span>{{ $pqr->tipoPqr?->nombre ?? 'Sin tipo' }}</span>
                            <span class="deadline {{ $pqr->is_overdue ? 'overdue' : '' }}">
                                @if(in_array($pqr->estado, ['respondida', 'cerrada'], true))
                                    Gestión finalizada
                                @elseif($pqr->is_overdue)
                                    Plazo vencido
                                @elseif($pqr->remaining_days === null)
                                    Sin plazo definido
                                @elseif($pqr->remaining_days < 0)
                                    Venció hace {{ abs($pqr->remaining_days) }} {{ abs($pqr->remaining_days) === 1 ? 'día hábil' : 'días hábiles' }}
                                @else
                                    @if($pqr->remaining_days === 0)Vence hoy @elseif($pqr->remaining_days === 1)Vence en 1 día hábil @else Vence en {{ $pqr->remaining_days }} días hábiles @endif
                                @endif
                            </span>
                        </div>
                        <div class="request-card-owner"><span>Responsable</span><b>{{ $pqr->assignee?->name ?? 'Sin asignar' }}</b></div>
                        <span class="open-case">Abrir expediente <span aria-hidden="true">→</span></span>
                    </a>
                    @can('update', $pqr)
                        <details class="mobile-quick-action quick-menu"><summary>Acción rápida</summary><form method="POST" action="{{ route('pqrs.quick-update',$pqr) }}" data-confirm="Actualizar solicitud" data-confirm-message="El cambio quedará registrado en el historial.">@csrf @method('PATCH')<label>Estado<select name="estado"><option value="radicada" @selected($pqr->estado==='radicada')>Radicada</option><option value="en_revision" @selected($pqr->estado==='en_revision')>En revisión</option><option value="respondida" @selected($pqr->estado==='respondida')>Respondida</option><option value="cerrada" @selected($pqr->estado==='cerrada')>Cerrada</option></select></label><label>Prioridad<select name="prioridad"><option value="alta" @selected($pqr->prioridad==='alta')>Alta</option><option value="media" @selected($pqr->prioridad==='media')>Media</option><option value="baja" @selected($pqr->prioridad==='baja')>Baja</option></select></label><label>Responsable<select name="assigned_to_id"><option value="">Sin asignar</option>@foreach($gestores as $gestor)<option value="{{ $gestor->id }}" @selected($pqr->assigned_to_id===$gestor->id)>{{ $gestor->name }}</option>@endforeach</select></label><button class="button primary">Aplicar</button></form></details>
                    @endcan
                </article>
            @empty
                <x-empty-state title="No hay PQRS para mostrar" :description="request()->hasAny(['buscar', 'estado', 'tipo_pqr_id', 'assigned_to_id', 'prioridad', 'desde', 'hasta']) ? 'No encontramos resultados con los filtros aplicados.' : 'Aún no hay PQRS autorizadas en este contexto.'" class="empty-state mobile-empty">@if(request()->hasAny(['buscar', 'estado', 'tipo_pqr_id', 'assigned_to_id', 'prioridad', 'desde', 'hasta']))<a class="button subtle" href="{{ route('pqrs.index') }}">Restablecer filtros</a>@elseif($navegacion['crearPqrs'] ?? false)<a href="{{ route('pqrs.create') }}" class="button primary">Radicar PQRS</a>@endif</x-empty-state>
            @endforelse
        </div>

        <div class="table-wrap">
            <table>
                <thead><tr><th>Radicado</th><th>Solicitud</th><th>Tipo</th>@if($puedeGestionar)<th>Prioridad</th>@endif<th>Estado</th><th>Vencimiento</th><th>Responsable</th><th><span class="sr-only">Acciones</span></th></tr></thead>
                <tbody>
                @forelse ($pqrs as $pqr)
                    <tr>
                        <td><span class="radicado">PQR-{{ str_pad($pqr->id, 4, '0', STR_PAD_LEFT) }}</span><small>Radicada {{ $pqr->fecha_radicacion->format('d/m/Y') }}</small></td>
                        <td><a class="subject-link" href="{{ route('pqrs.show', $pqr) }}"><strong class="subject">{{ $pqr->asunto }}</strong><small>{{ Str::limit($pqr->descripcion, 48) }}</small></a></td>
                        <td><span class="type-label">{{ $pqr->tipoPqr?->nombre ?? 'Sin tipo' }}</span></td>
                        @if($puedeGestionar)<td><span class="priority-badge prioridad-{{ $pqr->prioridad }}" aria-label="Prioridad {{ $pqr->prioridad_label }}">{{ $pqr->prioridad_label }}</span></td>@endif
                        <td><span class="status {{ $pqr->estado }}"><i aria-hidden="true"></i>{{ $pqr->estado_label }}</span></td>
                        <td><span class="deadline {{ $pqr->is_overdue ? 'overdue' : '' }}">{{ $pqr->fecha_limite_respuesta ? str_replace('.', '', $pqr->fecha_limite_respuesta->locale('es')->translatedFormat('d M Y')) : 'Sin definir' }}</span><small class="{{ $pqr->is_overdue ? 'danger-text' : '' }}">@if(in_array($pqr->estado, ['respondida', 'cerrada'], true))Gestión finalizada @elseif($pqr->is_overdue)Plazo vencido · {{ abs($pqr->remaining_days) }} {{ abs($pqr->remaining_days) === 1 ? 'día hábil' : 'días hábiles' }} @elseif($pqr->remaining_days === null)Sin plazo definido @elseif($pqr->remaining_days === 0)Vence hoy @elseif($pqr->remaining_days === 1)1 día hábil restante @else{{ $pqr->remaining_days }} días hábiles restantes @endif</small></td>
                        <td><span class="owner"><span class="avatar small">{{ Str::upper(Str::substr($pqr->assignee?->name ?? 'SA', 0, 2)) }}</span>{{ $pqr->assignee?->name ?? 'Sin asignar' }}</span></td>
                        <td class="actions">@can('update', $pqr)<details class="quick-menu"><summary class="icon-button" aria-label="Acciones rápidas para {{ $pqr->asunto }}">•••</summary><div><strong>Acción rápida</strong><form method="POST" action="{{ route('pqrs.quick-update',$pqr) }}" data-confirm="Actualizar solicitud" data-confirm-message="Se registrará este cambio en el historial y se notificará al residente si cambia el estado.">@csrf @method('PATCH')<label>Estado<select name="estado"><option value="radicada" @selected($pqr->estado==='radicada')>Radicada</option><option value="en_revision" @selected($pqr->estado==='en_revision')>En revisión</option><option value="respondida" @selected($pqr->estado==='respondida')>Respondida</option><option value="cerrada" @selected($pqr->estado==='cerrada')>Cerrada</option></select></label><label>Prioridad<select name="prioridad"><option value="alta" @selected($pqr->prioridad==='alta')>Alta</option><option value="media" @selected($pqr->prioridad==='media')>Media</option><option value="baja" @selected($pqr->prioridad==='baja')>Baja</option></select></label><label>Responsable<select name="assigned_to_id"><option value="">Sin asignar</option>@foreach($gestores as $gestor)<option value="{{ $gestor->id }}" @selected($pqr->assigned_to_id===$gestor->id)>{{ $gestor->name }}</option>@endforeach</select></label><button class="button primary">Aplicar</button><a href="{{ route('pqrs.edit',$pqr) }}">Edición completa</a></form></div></details>@else<a href="{{ route('pqrs.show', $pqr) }}" class="icon-button" aria-label="Abrir expediente de {{ $pqr->asunto }}">→</a>@endcan</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $puedeGestionar ? 8 : 7 }}"><x-empty-state title="No hay PQRS para mostrar" :description="request()->hasAny(['buscar', 'estado', 'tipo_pqr_id', 'assigned_to_id', 'prioridad', 'desde', 'hasta']) ? 'No encontramos resultados con los filtros aplicados.' : 'Aún no hay PQRS autorizadas en este contexto.'">@if(request()->hasAny(['buscar', 'estado', 'tipo_pqr_id', 'assigned_to_id', 'prioridad', 'desde', 'hasta']))<a class="button subtle" href="{{ route('pqrs.index') }}">Restablecer filtros</a>@elseif($navegacion['crearPqrs'] ?? false)<a href="{{ route('pqrs.create') }}" class="button primary">Radicar PQRS</a>@endif</x-empty-state></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="panel-footer"><span>Mostrando {{ $pqrs->firstItem() ?? 0 }}–{{ $pqrs->lastItem() ?? 0 }} de {{ $pqrs->total() }} PQRS</span><nav class="simple-pagination" aria-label="Paginación de PQRS">@if($pqrs->onFirstPage())<span>← Anterior</span>@else<a href="{{ $pqrs->previousPageUrl() }}">← Anterior</a>@endif<strong>Página {{ $pqrs->currentPage() }} de {{ $pqrs->lastPage() }}</strong>@if($pqrs->hasMorePages())<a href="{{ $pqrs->nextPageUrl() }}">Siguiente →</a>@else<span>Siguiente →</span>@endif</nav></div>
    </section>
@endsection
