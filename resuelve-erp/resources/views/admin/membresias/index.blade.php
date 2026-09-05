@extends('layouts.admin')

@section('titulo', 'Gestión de Membresías')
@section('titulo_pagina', 'Membresías')
@section('eyebrow', 'Gestión')

@section('contenido')
<a href="{{ route('admin.index') }}" class="back-link">← Volver al dashboard</a>

<x-page-heading
    title="Gestión de Membresías"
    eyebrow="Gestión"
>
    <x-slot name="actions">
        <a href="{{ route('admin.membresias.create') }}" class="button primary">
            ✚ Nueva membresía
        </a>
    </x-slot>
</x-page-heading>

 {{-- Filtros --}}
<div class="panel" style="margin-bottom:18px">
    <div class="panel-header">
        <div>
            <h2>Filtros</h2>
            <p>Buscar y filtrar membresías por criterios.</p>
        </div>
    </div>
    <form method="GET" action="{{ route('admin.membresias.index') }}" class="filters" style="padding:14px 21px;border-top:1px solid var(--line)">
        <div class="search-box">
            <span>🔍</span>
            <input type="text" name="search" placeholder="Buscar por nombre o email..."
                   value="{{ request('search') }}">
        </div>
        <select name="copropiedad_id">
            <option value="">Todas las copropiedades</option>
            @foreach(\App\Models\Copropiedad::orderBy('nombre')->get() as $copropiedad)
                <option value="{{ $copropiedad->id }}"
                    {{ request('copropiedad_id') == $copropiedad->id ? 'selected' : '' }}>
                    {{ $copropiedad->nombre }}
                </option>
            @endforeach
        </select>
        <select name="estado">
            <option value="">Todos los estados</option>
            <option value="activa" {{ request('estado') === 'activa' ? 'selected' : '' }}>Activa</option>
            <option value="suspendida" {{ request('estado') === 'suspendida' ? 'selected' : '' }}>Suspendida</option>
            <option value="finalizada" {{ request('estado') === 'finalizada' ? 'selected' : '' }}>Finalizada</option>
        </select>
        <button type="submit" class="button primary" style="white-space:nowrap">Filtrar</button>
        @if(request()->hasAny(['search', 'copropiedad_id', 'estado']))
            <a href="{{ route('admin.membresias.index') }}" class="clear-filter">Limpiar filtros</a>
        @endif
    </form>
</div>

 {{-- Tabla --}}
<div class="panel">
    <div class="panel-header">
        <div>
            <h2>Listado de membresías</h2>
            <p>{{ $membresias->total() }} membresía{{ $membresias->total() !== 1 ? 's' : '' }} encontrada{{ $membresias->total() !== 1 ? 's' : '' }}</p>
        </div>
    </div>

    @if($membresias->isEmpty())
        <x-empty-state title="No se encontraron membresías" description="Crea una nueva membresía o ajusta los filtros de búsqueda.">
            <a href="{{ route('admin.membresias.create') }}" class="button primary">✚ Nueva membresía</a>
        </x-empty-state>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Copropiedad</th>
                        <th>Estado</th>
                        <th>Vigente desde</th>
                        <th>Vigente hasta</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($membresias as $membresia)
                        <tr>
                            <td>
                                <div class="owner">
                                    <span class="avatar small">{{ Str::upper(Str::substr($membresia->usuario->name ?? '?', 0, 2)) }}</span>
                                    <div>
                                        <strong>{{ $membresia->usuario->name ?? 'N/A' }}</strong>
                                        <small>{{ $membresia->usuario->email ?? '' }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $membresia->copropiedad->nombre ?? 'N/A' }}</td>
                            <td>
                                <x-badge :label="ucfirst($membresia->estado)" variant="status" :color="$membresia->estado" :icon="true" />
                            </td>
                            <td>{{ $membresia->vigente_desde->format('d/m/Y') }}</td>
                            <td>{{ $membresia->vigente_hasta?->format('d/m/Y') ?? '—' }}</td>
                            <td class="actions">
                                <a href="{{ route('admin.membresias.show', $membresia) }}" class="icon-button" title="Ver detalle">→</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($membresias->hasPages())
            <div class="panel-footer">
                <span>Mostrando {{ $membresias->firstItem() }}–{{ $membresias->lastItem() }} de {{ $membresias->total() }}</span>
                {{ $membresias->withQueryString()->links() }}
            </div>
        @endif
    @endif
</div>
@endsection
