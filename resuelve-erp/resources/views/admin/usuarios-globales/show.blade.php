@extends('layouts.admin')

@section('titulo', 'Detalle de Usuario')
@section('titulo_pagina', 'Usuario')
@section('eyebrow', 'Plataforma')

@section('contenido')
<a href="{{ route('admin.usuarios-globales.index') }}" class="back-link">← Volver a usuarios</a>

<x-page-heading :title="$usuario->name" :description="$usuario->email">
    <x-slot name="actions">
        <x-badge :label="ucfirst($usuario->estado ?? 'activo')" variant="status" :color="($usuario->estado ?? 'activo') === 'activo' ? 'activo' : 'cerrada'" :icon="true" />
        @if($usuario->es_administrador_sistema)
            <span class="user-badge-sistema">🛡 Admin Sistema</span>
        @endif
        <a href="{{ route('admin.usuarios-globales.edit', $usuario) }}" class="button subtle">✎ Editar</a>
    </x-slot>
</x-page-heading>

<div class="detail-grid">
    <div class="detail-card">
        <div class="detail-section">
            <h2>Información general</h2>
            <div class="expediente-facts">
                <div>
                    <dt>Nombre</dt>
                    <dd>{{ $usuario->name }}</dd>
                </div>
                <div>
                    <dt>Email</dt>
                    <dd>{{ $usuario->email }}</dd>
                </div>
                <div>
                    <dt>Rol heredado</dt>
                    <dd>{{ ucfirst($usuario->role) }}</dd>
                </div>
                <div>
                    <dt>Torre</dt>
                    <dd>{{ $usuario->tower ?? 'No asignada' }}</dd>
                </div>
                <div>
                    <dt>Unidad</dt>
                    <dd>{{ $usuario->unit ?? 'No asignada' }}</dd>
                </div>
                <div>
                    <dt>Admin Sistema</dt>
                    <dd>{{ $usuario->es_administrador_sistema ? 'Sí' : 'No' }}</dd>
                </div>
                <div>
                    <dt>Estado</dt>
                    <dd>
                        <x-badge :label="ucfirst($usuario->estado ?? 'activo')" variant="status" :color="($usuario->estado ?? 'activo') === 'activo' ? 'activo' : 'cerrada'" :icon="true" />
                    </dd>
                </div>
                @if($usuario->desactivado_at)
                    <div>
                        <dt>Desactivado el</dt>
                        <dd>{{ $usuario->desactivado_at->format('d/m/Y H:i') }}</dd>
                    </div>
                @endif
                <div>
                    <dt>Creado el</dt>
                    <dd>{{ $usuario->created_at->format('d/m/Y H:i') }}</dd>
                </div>
            </div>
        </div>

        <div class="detail-section">
            <h2>Membresías ({{ $usuario->membresiasCopropiedad->count() }})</h2>
            @if($usuario->membresiasCopropiedad->isEmpty())
                <div class="empty-state" style="padding:16px">
                    <p>No hay membresías asociadas a este usuario.</p>
                </div>
            @else
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Organización</th>
                                <th>Copropiedad</th>
                                <th>Estado</th>
                                <th>Vigente desde</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($usuario->membresiasCopropiedad as $membresia)
                                <tr>
                                    <td>{{ $membresia->organizacion->nombre ?? 'N/A' }}</td>
                                    <td>{{ $membresia->copropiedad->nombre ?? 'N/A' }}</td>
                                    <td>
                                        <x-badge :label="ucfirst($membresia->estado)" variant="status" :color="$membresia->estado === 'activa' ? 'activo' : 'cerrada'" :icon="true" />
                                    </td>
                                    <td>{{ $membresia->vigente_desde->format('d/m/Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div>
        <div class="metadata-card">
            <h2>Acciones</h2>

            @if(($usuario->estado ?? 'activo') === 'activo')
                @unless($usuario->id === auth()->id())
                    <div style="margin-top:18px">
                        <form method="POST" action="{{ route('admin.usuarios-globales.desactivar', $usuario) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="button danger"
                                    onclick="return confirm('¿Está seguro de desactivar este usuario?')">
                                ⏸ Desactivar usuario
                            </button>
                        </form>
                    </div>
                @endunless
            @else
                <div style="margin-top:18px">
                    <form method="POST" action="{{ route('admin.usuarios-globales.reactivar', $usuario) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="button primary"
                                onclick="return confirm('¿Está seguro de reactivar este usuario?')">
                            ✓ Reactivar usuario
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
