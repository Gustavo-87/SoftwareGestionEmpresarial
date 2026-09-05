@extends('layouts.admin')

@section('titulo', 'Detalle de Membresía')
@section('titulo_pagina', 'Membresía')
@section('eyebrow', 'Gestión')

@section('contenido')
<a href="{{ route('admin.membresias.index') }}" class="back-link">← Volver a membresías</a>

<x-page-heading :title="$membresia->usuario->name ?? 'Sin usuario'" :description="($membresia->copropiedad->nombre ?? 'Sin copropiedad') . ' · ' . ($membresia->organizacion->nombre ?? 'Sin organización')">
    <x-slot name="actions">
        <x-badge :label="ucfirst($membresia->estado)" variant="status" :color="$membresia->estado" :icon="true" />
        <a href="{{ route('admin.membresias.edit', $membresia) }}" class="button subtle">✎ Editar</a>
    </x-slot>
</x-page-heading>

<div class="detail-grid">
    {{-- Panel principal --}}
    <div class="detail-card">
        <div class="detail-section">
            <h2>Información general</h2>
            <div class="expediente-facts">
                <div>
                    <dt>Usuario</dt>
                    <dd>{{ $membresia->usuario->name ?? 'N/A' }} <span style="color:var(--muted);font-weight:400;font-size:10px">({{ $membresia->usuario->email ?? 'N/A' }})</span></dd>
                </div>
                <div>
                    <dt>Organización</dt>
                    <dd>{{ $membresia->organizacion->nombre ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt>Copropiedad</dt>
                    <dd>{{ $membresia->copropiedad->nombre ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt>Vigente desde</dt>
                    <dd>{{ $membresia->vigente_desde->format('d/m/Y H:i') }}</dd>
                </div>
                <div>
                    <dt>Vigente hasta</dt>
                    <dd>{{ $membresia->vigente_hasta?->format('d/m/Y H:i') ?? 'Indefinida' }}</dd>
                </div>
                <div>
                    <dt>Creada por</dt>
                    <dd>{{ $membresia->creador?->name ?? 'Sistema' }}</dd>
                </div>
                @if($membresia->motivo_terminacion)
                    <div>
                        <dt>Motivo de terminación</dt>
                        <dd>{{ $membresia->motivo_terminacion }}</dd>
                    </div>
                @endif
            </div>
        </div>

        {{-- Roles asignados --}}
        <div class="detail-section">
            <h2>Roles asignados</h2>
            @if($membresia->roles->isEmpty())
                <div class="empty-state" style="padding:16px">
                    <p>No existen roles asignados a esta membresía. Puedes agregar roles desde la sección Asignar nuevo rol ubicada más abajo.</p>
                </div>
            @else
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Rol</th>
                                <th>Estado</th>
                                <th>Vigente desde</th>
                                <th>Vigente hasta</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($membresia->roles as $rol)
                                <tr>
                                    <td>
                                        <span class="role-badge">{{ $rol->nombre }}</span>
                                    </td>
                                    <td>
                                        @if($rol->pivot->estado === 'activa')
                                            <x-badge label="Activa" variant="status" color="activo" :icon="true" />
                                        @else
                                            <x-badge :label="$rol->pivot->estado" variant="status" :icon="true" />
                                        @endif
                                    </td>
                                    <td>{{ $rol->pivot->vigente_desde?->format('d/m/Y') ?? '—' }}</td>
                                    <td>{{ $rol->pivot->vigente_hasta?->format('d/m/Y') ?? '—' }}</td>
                                    <td class="actions">
                                        <form method="POST" action="{{ route('admin.membresias.roles.destroy', [$membresia, $rol->id]) }}" style="display:inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="button danger" style="padding:6px 10px;font-size:9px"
                                                    onclick="return confirm('¿Revocar este rol?')">
                                                Revocar
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Asignar nuevo rol --}}
        <div class="detail-section">
            <h2>Asignar nuevo rol</h2>
            <form method="POST" action="{{ route('admin.membresias.roles.store', $membresia) }}">
                @csrf
                <div class="form-grid" style="padding:0">
                    <div class="field">
                        <label for="rol_id">Nuevo Rol</label>
                        <select name="rol_id" id="rol_id" required>
                            <option value="">Seleccionar rol...</option>
                            @foreach(\App\Models\Rol::where('ambito_aplicable', 'copropiedad')->where('estado', 'activo')->orderBy('nombre')->get() as $rol)
                                <option value="{{ $rol->id }}">{{ $rol->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="rol_vigente_desde">Vigente desde</label>
                        <input type="date" name="vigente_desde" id="rol_vigente_desde"
                               value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="field">
                        <label for="rol_vigente_hasta">Vigente hasta</label>
                        <input type="date" name="vigente_hasta" id="rol_vigente_hasta">
                    </div>
                    <div class="field" style="justify-content:flex-end">
                        <button type="submit" class="button primary">Asignar rol</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Panel lateral: acciones --}}
    <div>
        <div class="metadata-card">
            <h2>Acciones</h2>

            @if($membresia->estado === 'activa')
                <div style="margin-top:18px">
                    <form method="POST" action="{{ route('admin.membresias.suspend', $membresia) }}">
                        @csrf
                        @method('PATCH')
                        <div class="field" style="margin-bottom:12px">
                            <label for="motivo_suspension">Motivo de suspensión *</label>
                            <textarea name="motivo" id="motivo_suspension" rows="3" required
                                      class="field-textarea"></textarea>
                        </div>
                        <button type="submit" class="button danger"
                                onclick="return confirm('¿Está seguro de suspender esta membresía?')">
                            ⏸ Suspender membresía
                        </button>
                    </form>
                </div>
                <hr style="border:0;border-top:1px solid var(--line);margin:18px 0">
            @endif

            @if(in_array($membresia->estado, ['activa', 'suspendida']))
                <div>
                    <form method="POST" action="{{ route('admin.membresias.finalize', $membresia) }}">
                        @csrf
                        @method('PATCH')
                        <div class="field" style="margin-bottom:12px">
                            <label for="motivo_finalizacion">Motivo de finalización *</label>
                            <textarea name="motivo" id="motivo_finalizacion" rows="3" required
                                      class="field-textarea"></textarea>
                        </div>
                        <button type="submit" class="button danger"
                                onclick="return confirm('¿Está seguro de finalizar esta membresía? Esta acción no se puede deshacer.')">
                            ✕ Finalizar membresía
                        </button>
                    </form>
                </div>
            @else
                <div class="empty-state" style="padding:14px">
                    <p>No hay acciones disponibles para esta membresía.</p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
