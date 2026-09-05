@extends('layouts.admin')

@section('titulo', 'Detalle de Copropiedad')
@section('titulo_pagina', 'Copropiedad')
@section('eyebrow', 'Plataforma')

@section('contenido')
<a href="{{ route('admin.copropiedades.index') }}" class="back-link">← Volver a copropiedades</a>

<x-page-heading :title="$copropiedad->nombre" :description="($copropiedad->organizacion->nombre ?? 'Sin organización') . ' · ' . ($copropiedad->ciudad ?? 'Sin ciudad')">
    <x-slot name="actions">
        <x-badge :label="ucfirst($copropiedad->estado)" variant="status" :color="$copropiedad->estado === 'activa' ? 'activo' : 'cerrada'" :icon="true" />
        <a href="{{ route('admin.copropiedades.edit', $copropiedad) }}" class="button subtle">✎ Editar</a>
    </x-slot>
</x-page-heading>

<div class="detail-grid">
    <div class="detail-card">
        <div class="detail-section">
            <h2>Información general</h2>
            <div class="expediente-facts">
                <div>
                    <dt>Nombre</dt>
                    <dd>{{ $copropiedad->nombre }}</dd>
                </div>
                <div>
                    <dt>Organización</dt>
                    <dd>{{ $copropiedad->organizacion->nombre ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt>NIT</dt>
                    <dd>{{ $copropiedad->nit ?? 'No registrado' }}</dd>
                </div>
                <div>
                    <dt>Representante legal</dt>
                    <dd>{{ $copropiedad->representante_legal ?? 'No registrado' }}</dd>
                </div>
                <div>
                    <dt>Ciudad</dt>
                    <dd>{{ $copropiedad->ciudad ?? 'No registrada' }}</dd>
                </div>
                <div>
                    <dt>Dirección</dt>
                    <dd>{{ $copropiedad->direccion ?? 'No registrada' }}</dd>
                </div>
                <div>
                    <dt>Email</dt>
                    <dd>{{ $copropiedad->email ?? 'No registrado' }}</dd>
                </div>
                <div>
                    <dt>Teléfono</dt>
                    <dd>{{ $copropiedad->telefono ?? 'No registrado' }}</dd>
                </div>
                <div>
                    <dt>Estado</dt>
                    <dd>
                        <x-badge :label="ucfirst($copropiedad->estado)" variant="status" :color="$copropiedad->estado === 'activa' ? 'activo' : 'cerrada'" :icon="true" />
                    </dd>
                </div>
                @if($copropiedad->desactivada_at)
                    <div>
                        <dt>Desactivada el</dt>
                        <dd>{{ $copropiedad->desactivada_at->format('d/m/Y H:i') }}</dd>
                    </div>
                @endif
                <div>
                    <dt>Creada el</dt>
                    <dd>{{ $copropiedad->created_at->format('d/m/Y H:i') }}</dd>
                </div>
            </div>
        </div>

        <div class="detail-section">
            <h2>Membresías ({{ $copropiedad->membresiasCopropiedad->count() }})</h2>
            @if($copropiedad->membresiasCopropiedad->isEmpty())
                <div class="empty-state" style="padding:16px">
                    <p>No hay membresías registradas para esta copropiedad.</p>
                </div>
            @else
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Estado</th>
                                <th>Vigente desde</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($copropiedad->membresiasCopropiedad as $membresia)
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

            @if($copropiedad->estado === 'activa')
                <div style="margin-top:18px">
                    <form method="POST" action="{{ route('admin.copropiedades.desactivar', $copropiedad) }}">
                        @csrf
                        @method('PATCH')
                        <div class="field" style="margin-bottom:12px">
                            <label for="motivo">Motivo de desactivación *</label>
                            <textarea name="motivo" id="motivo" rows="3" required
                                      class="field-textarea"></textarea>
                        </div>
                        <button type="submit" class="button danger"
                                onclick="return confirm('¿Está seguro de desactivar esta copropiedad?')">
                            ⏸ Desactivar copropiedad
                        </button>
                    </form>
                </div>
            @else
                <div style="margin-top:18px">
                    <form method="POST" action="{{ route('admin.copropiedades.reactivar', $copropiedad) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="button primary"
                                onclick="return confirm('¿Está seguro de reactivar esta copropiedad?')">
                            ✓ Reactivar copropiedad
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
