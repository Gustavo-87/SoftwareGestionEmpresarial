@extends('layouts.admin')

@section('titulo', 'Catálogo de permisos')
@section('eyebrow', 'Administración del sistema')
@section('titulo_pagina', 'Catálogo de permisos')

@section('contenido')

<x-page-heading title="Catálogo de permisos" eyebrow="Administración del sistema">
    <x-slot name="actions"><a class="button primary" href="{{ route('admin.permisos.create') }}">✚ Nuevo permiso</a></x-slot>
</x-page-heading>

<p>Alta y eliminación lógica del catálogo de permisos. El rol admin base hereda
los permisos nuevos; los permisos base del sistema están protegidos. Un permiso
desactivado se conserva en el registro, no concede acceso y no admite nuevas
asignaciones.</p>

@error('permiso')
    <p class="field-error" style="margin-bottom:12px" role="alert">{{ $message }}</p>
@enderror

<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Clave</th>
                <th>Nombre</th>
                <th>Módulo</th>
                <th>Estado</th>
                <th>Uso</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach($permisos as $permiso)
                <tr>
                    <td><code>{{ $permiso->name }}</code></td>
                    <td>{{ $permiso->nombre ?: $permiso->name }}</td>
                    <td>{{ $permiso->modulo ?: \Illuminate\Support\Str::before($permiso->name, '.') }}</td>
                    <td>
                        <span class="status {{ ($permiso->estado ?? 'activo') === 'activo' ? 'activo' : 'archivado' }}">
                            <i></i>{{ ($permiso->estado ?? 'activo') === 'activo' ? 'Activo' : 'Desactivado' }}
                        </span>
                    </td>
                    <td>
                        @php($uso = $usos[$permiso->id])
                        @if($uso['roles'] !== [])
                            Roles: {{ implode(', ', $uso['roles']) }}
                        @elseif($uso['usuarios'] !== [])
                            Usuarios: {{ implode(', ', $uso['usuarios']) }}
                        @elseif($uso['heredado_admin'])
                            Solo herencia automática del rol admin base
                        @else
                            Sin uso
                        @endif
                    </td>
                    <td class="actions">
                        <div class="user-actions">
                            @if(\App\Application\Roles\CatalogoPermisosBase::esBase($permiso))
                                <span class="type-label">Protegido</span>
                            @elseif(($permiso->estado ?? 'activo') === 'activo')
                                <form method="POST" action="{{ route('admin.permisos.desactivar', $permiso) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="button subtle danger"
                                            onclick="return confirm('¿Desactivar el permiso «{{ $permiso->name }}»?\n\nNo concede acceso ni admite nuevas asignaciones; el registro se conserva.')">Desactivar</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.permisos.reactivar', $permiso) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="button subtle">Reactivar</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

@endsection
