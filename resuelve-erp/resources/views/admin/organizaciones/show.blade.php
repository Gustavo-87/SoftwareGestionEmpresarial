@extends('layouts.admin')

@section('titulo', 'Detalle de Organización')
@section('titulo_pagina', 'Organización')
@section('eyebrow', 'Plataforma')

@section('contenido')
<a href="{{ route('admin.organizaciones.index') }}" class="back-link">← Volver a organizaciones</a>

<x-page-heading :title="$organizacion->nombre" :description="($organizacion->identificacion_tributaria ?? 'Sin NIT') . ' · ' . ($organizacion->email ?? 'Sin email')">
    <x-slot name="actions">
        <x-badge :label="ucfirst($organizacion->estado)" variant="status" :color="$organizacion->estado === 'activa' ? 'activo' : 'cerrada'" :icon="true" />
        <a href="{{ route('admin.organizaciones.edit', $organizacion) }}" class="button subtle">✎ Editar</a>
    </x-slot>
</x-page-heading>

<div class="detail-grid">
    <div class="detail-card">
        <div class="detail-section">
            <h2>Información general</h2>
            <div class="expediente-facts">
                <div>
                    <dt>Nombre</dt>
                    <dd>{{ $organizacion->nombre }}</dd>
                </div>
                <div>
                    <dt>NIT</dt>
                    <dd>{{ $organizacion->identificacion_tributaria ?? 'No registrado' }}</dd>
                </div>
                <div>
                    <dt>Email</dt>
                    <dd>{{ $organizacion->email ?? 'No registrado' }}</dd>
                </div>
                <div>
                    <dt>Teléfono</dt>
                    <dd>{{ $organizacion->telefono ?? 'No registrado' }}</dd>
                </div>
                <div>
                    <dt>Estado</dt>
                    <dd>
                        <x-badge :label="ucfirst($organizacion->estado)" variant="status" :color="$organizacion->estado === 'activa' ? 'activo' : 'cerrada'" :icon="true" />
                    </dd>
                </div>
                @if($organizacion->desactivada_at)
                    <div>
                        <dt>Desactivada el</dt>
                        <dd>{{ $organizacion->desactivada_at->format('d/m/Y H:i') }}</dd>
                    </div>
                @endif
                <div>
                    <dt>Creada el</dt>
                    <dd>{{ $organizacion->created_at->format('d/m/Y H:i') }}</dd>
                </div>
            </div>
        </div>

        <div class="detail-section">
            <h2>Copropiedades ({{ $organizacion->copropiedades->count() }})</h2>
            @if($organizacion->copropiedades->isEmpty())
                <div class="empty-state" style="padding:16px">
                    <p>No hay copropiedades registradas para esta organización.</p>
                </div>
            @else
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>NIT</th>
                                <th>Ciudad</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($organizacion->copropiedades as $copropiedad)
                                <tr>
                                    <td><strong>{{ $copropiedad->nombre }}</strong></td>
                                    <td>{{ $copropiedad->nit ?? '—' }}</td>
                                    <td>{{ $copropiedad->ciudad ?? '—' }}</td>
                                    <td>
                                        <x-badge :label="ucfirst($copropiedad->estado)" variant="status" :color="$copropiedad->estado === 'activa' ? 'activo' : 'cerrada'" :icon="true" />
                                    </td>
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

            @if($organizacion->estado === 'activa')
                <div style="margin-top:18px">
                    <form method="POST" action="{{ route('admin.organizaciones.desactivar', $organizacion) }}">
                        @csrf
                        @method('PATCH')
                        <div class="field" style="margin-bottom:12px">
                            <label for="motivo">Motivo de desactivación *</label>
                            <textarea name="motivo" id="motivo" rows="3" required
                                      class="field-textarea"></textarea>
                        </div>
                        <button type="submit" class="button danger"
                                onclick="return confirm('¿Está seguro de desactivar esta organización?')">
                            ⏸ Desactivar organización
                        </button>
                    </form>
                </div>
            @else
                <div style="margin-top:18px">
                    <form method="POST" action="{{ route('admin.organizaciones.reactivar', $organizacion) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="button primary"
                                onclick="return confirm('¿Está seguro de reactivar esta organización?')">
                            ✓ Reactivar organización
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
