@extends('layouts.admin')

@section('titulo', 'Organizaciones')
@section('titulo_pagina', 'Organizaciones')
@section('eyebrow', 'Plataforma')

@section('contenido')
<a href="{{ route('admin.index') }}" class="back-link">← Volver al dashboard</a>

<x-page-heading
    title="Organizaciones"
    eyebrow="Plataforma"
>
    <x-slot name="actions">
        <a href="{{ route('admin.organizaciones.create') }}" class="button primary">
            ✚ Nueva organización
        </a>
    </x-slot>
</x-page-heading>

<div class="panel" style="margin-bottom:18px">
    <div class="panel-header">
        <div>
            <h2>Filtros</h2>
            <p>Buscar y filtrar organizaciones.</p>
        </div>
    </div>
    <form method="GET" action="{{ route('admin.organizaciones.index') }}" class="filters" style="padding:14px 21px;border-top:1px solid var(--line)">
        <div class="search-box">
            <span>🔍</span>
            <input type="text" name="search" placeholder="Buscar por nombre, NIT o email..."
                   value="{{ request('search') }}">
        </div>
        <select name="estado">
            <option value="">Todos los estados</option>
            <option value="activa" {{ request('estado') === 'activa' ? 'selected' : '' }}>Activa</option>
            <option value="inactiva" {{ request('estado') === 'inactiva' ? 'selected' : '' }}>Inactiva</option>
        </select>
        <button type="submit" class="button primary" style="white-space:nowrap">Filtrar</button>
        @if(request()->hasAny(['search', 'estado']))
            <a href="{{ route('admin.organizaciones.index') }}" class="clear-filter">Limpiar filtros</a>
        @endif
    </form>
</div>

<div class="panel">
    <div class="panel-header">
        <div>
            <h2>Listado de organizaciones</h2>
            <p>{{ $organizaciones->total() }} organización(es) encontrada(s)</p>
        </div>
    </div>

    @if($organizaciones->isEmpty())
        <x-empty-state title="No se encontraron organizaciones" description="Crea una nueva organización para comenzar.">
            <a href="{{ route('admin.organizaciones.create') }}" class="button primary">✚ Nueva organización</a>
        </x-empty-state>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>NIT</th>
                        <th>Email</th>
                        <th>Estado</th>
                        <th>Copropiedades</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($organizaciones as $organizacion)
                        <tr>
                            <td>
                                <div class="owner">
                                    <span class="avatar small">{{ Str::upper(Str::substr($organizacion->nombre, 0, 2)) }}</span>
                                    <div>
                                        <strong>{{ $organizacion->nombre }}</strong>
                                        <small>{{ $organizacion->telefono ?? '' }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $organizacion->identificacion_tributaria ?? '—' }}</td>
                            <td>{{ $organizacion->email ?? '—' }}</td>
                            <td>
                                <x-badge :label="ucfirst($organizacion->estado)" variant="status" :color="$organizacion->estado === 'activa' ? 'activo' : 'cerrada'" :icon="true" />
                            </td>
                            <td>{{ $organizacion->copropiedades->count() }}</td>
                            <td class="actions">
                                <a href="{{ route('admin.organizaciones.show', $organizacion) }}" class="icon-button" title="Ver detalle">→</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($organizaciones->hasPages())
            <div class="panel-footer">
                <span>Mostrando {{ $organizaciones->firstItem() }}–{{ $organizaciones->lastItem() }} de {{ $organizaciones->total() }}</span>
                {{ $organizaciones->withQueryString()->links() }}
            </div>
        @endif
    @endif
</div>
@endsection
