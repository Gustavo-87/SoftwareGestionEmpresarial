@extends('layouts.admin')

@section('titulo', 'Usuarios Globales')
@section('titulo_pagina', 'Usuarios')
@section('eyebrow', 'Plataforma')

@section('contenido')
<a href="{{ route('admin.index') }}" class="back-link">← Volver al dashboard</a>

<x-page-heading
    title="Usuarios Globales"
    eyebrow="Plataforma"
>
    <x-slot name="actions">
        <a href="{{ route('admin.usuarios-globales.create') }}" class="button primary">
            ✚ Nuevo usuario
        </a>
    </x-slot>
</x-page-heading>

<div class="panel" style="margin-bottom:18px">
    <div class="panel-header">
        <div>
            <h2>Filtros</h2>
            <p>Buscar y filtrar usuarios.</p>
        </div>
    </div>
    <form method="GET" action="{{ route('admin.usuarios-globales.index') }}" class="filters" style="padding:14px 21px;border-top:1px solid var(--line)">
        <div class="search-box">
            <span>🔍</span>
            <input type="text" name="search" placeholder="Buscar por nombre o email..."
                   value="{{ request('search') }}">
        </div>
        <select name="estado">
            <option value="">Todos los estados</option>
            <option value="activo" {{ request('estado') === 'activo' ? 'selected' : '' }}>Activo</option>
            <option value="inactivo" {{ request('estado') === 'inactivo' ? 'selected' : '' }}>Inactivo</option>
        </select>
        <select name="es_administrador_sistema">
            <option value="">Todos</option>
            <option value="1" {{ request('es_administrador_sistema') === '1' ? 'selected' : '' }}>Administrador sistema</option>
            <option value="0" {{ request('es_administrador_sistema') === '0' ? 'selected' : '' }}>No administrador</option>
        </select>
        <button type="submit" class="button primary" style="white-space:nowrap">Filtrar</button>
        @if(request()->hasAny(['search', 'estado', 'es_administrador_sistema']))
            <a href="{{ route('admin.usuarios-globales.index') }}" class="clear-filter">Limpiar filtros</a>
        @endif
    </form>
</div>

<div class="panel">
    <div class="panel-header">
        <div>
            <h2>Listado de usuarios</h2>
            <p>{{ $usuarios->total() }} usuario(s) encontrado(s)</p>
        </div>
    </div>

    @if($usuarios->isEmpty())
        <x-empty-state title="No se encontraron usuarios" description="Crea un nuevo usuario para comenzar.">
            <a href="{{ route('admin.usuarios-globales.create') }}" class="button primary">✚ Nuevo usuario</a>
        </x-empty-state>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Email</th>
                        <th>Estado</th>
                        <th>Admin Sistema</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($usuarios as $usuario)
                        <tr>
                            <td>
                                <div class="owner">
                                    <span class="avatar small">{{ Str::upper(Str::substr($usuario->name, 0, 2)) }}</span>
                                    <div>
                                        <strong>{{ $usuario->name }}</strong>
                                        <small>{{ $usuario->role }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $usuario->email }}</td>
                            <td>
                                <x-badge :label="ucfirst($usuario->estado ?? 'activo')" variant="status" :color="$usuario->estado === 'activo' ? 'activo' : 'cerrada'" :icon="true" />
                            </td>
                            <td>
                                @if($usuario->es_administrador_sistema)
                                    <span class="user-badge-sistema">🛡 Sí</span>
                                @else
                                    <span style="color:var(--muted);font-size:9px">No</span>
                                @endif
                            </td>
                            <td class="actions">
                                <a href="{{ route('admin.usuarios-globales.show', $usuario) }}" class="icon-button" title="Ver detalle">→</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($usuarios->hasPages())
            <div class="panel-footer">
                <span>Mostrando {{ $usuarios->firstItem() }}–{{ $usuarios->lastItem() }} de {{ $usuarios->total() }}</span>
                {{ $usuarios->withQueryString()->links() }}
            </div>
        @endif
    @endif
</div>
@endsection
