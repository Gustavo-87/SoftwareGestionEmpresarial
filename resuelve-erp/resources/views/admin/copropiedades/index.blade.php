@extends('layouts.admin')

@section('titulo', 'Copropiedades')
@section('titulo_pagina', 'Copropiedades')
@section('eyebrow', 'Plataforma')

@section('contenido')
<a href="{{ route('admin.index') }}" class="back-link">← Volver al dashboard</a>

<x-page-heading
    title="Copropiedades"
    eyebrow="Plataforma"
>
    <x-slot name="actions">
        <a href="{{ route('admin.copropiedades.create') }}" class="button primary">
            ✚ Nueva copropiedad
        </a>
    </x-slot>
</x-page-heading>

<div class="panel" style="margin-bottom:18px">
    <div class="panel-header">
        <div>
            <h2>Filtros</h2>
            <p>Buscar y filtrar copropiedades.</p>
        </div>
    </div>
    <form method="GET" action="{{ route('admin.copropiedades.index') }}" class="filters" style="padding:14px 21px;border-top:1px solid var(--line)">
        <div class="search-box">
            <span>🔍</span>
            <input type="text" name="search" placeholder="Buscar por nombre, NIT o ciudad..."
                   value="{{ request('search') }}">
        </div>
        <select name="organizacion_id">
            <option value="">Todas las organizaciones</option>
            @foreach($organizaciones as $org)
                <option value="{{ $org->id }}" {{ request('organizacion_id') == $org->id ? 'selected' : '' }}>
                    {{ $org->nombre }}
                </option>
            @endforeach
        </select>
        <select name="estado">
            <option value="">Todos los estados</option>
            <option value="activa" {{ request('estado') === 'activa' ? 'selected' : '' }}>Activa</option>
            <option value="inactiva" {{ request('estado') === 'inactiva' ? 'selected' : '' }}>Inactiva</option>
        </select>
        <button type="submit" class="button primary" style="white-space:nowrap">Filtrar</button>
        @if(request()->hasAny(['search', 'organizacion_id', 'estado']))
            <a href="{{ route('admin.copropiedades.index') }}" class="clear-filter">Limpiar filtros</a>
        @endif
    </form>
</div>

<div class="panel">
    <div class="panel-header">
        <div>
            <h2>Listado de copropiedades</h2>
            <p>{{ $copropiedades->total() }} copropiedad(es) encontrada(s)</p>
        </div>
    </div>

    @if($copropiedades->isEmpty())
        <x-empty-state title="No se encontraron copropiedades" description="Crea una nueva copropiedad para comenzar.">
            <a href="{{ route('admin.copropiedades.create') }}" class="button primary">✚ Nueva copropiedad</a>
        </x-empty-state>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Organización</th>
                        <th>NIT</th>
                        <th>Ciudad</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($copropiedades as $copropiedad)
                        <tr>
                            <td>
                                <div class="owner">
                                    <span class="avatar small">{{ Str::upper(Str::substr($copropiedad->nombre, 0, 2)) }}</span>
                                    <div>
                                        <strong>{{ $copropiedad->nombre }}</strong>
                                        <small>{{ $copropiedad->email ?? '' }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $copropiedad->organizacion->nombre ?? '—' }}</td>
                            <td>{{ $copropiedad->nit ?? '—' }}</td>
                            <td>{{ $copropiedad->ciudad ?? '—' }}</td>
                            <td>
                                <x-badge :label="ucfirst($copropiedad->estado)" variant="status" :color="$copropiedad->estado === 'activa' ? 'activo' : 'cerrada'" :icon="true" />
                            </td>
                            <td class="actions">
                                <a href="{{ route('admin.copropiedades.show', $copropiedad) }}" class="icon-button" title="Ver detalle">→</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($copropiedades->hasPages())
            <div class="panel-footer">
                <span>Mostrando {{ $copropiedades->firstItem() }}–{{ $copropiedades->lastItem() }} de {{ $copropiedades->total() }}</span>
                {{ $copropiedades->withQueryString()->links() }}
            </div>
        @endif
    @endif
</div>
@endsection
