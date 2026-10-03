@extends(auth()->user()->esAdministradorSistema() ? 'layouts.admin' : 'layouts.app')
@section('titulo', 'Editar rol')
@section('titulo_pagina', 'Editar rol')
@section('contenido')
<x-page-heading :title="$rol->name" eyebrow="Administración de acceso">
    <x-slot name="actions"><a class="button secondary" href="{{ route('roles.index') }}">Volver a Roles y permisos</a></x-slot>
</x-page-heading>
<div class="maintenance-layout">
    <section class="panel maintenance-summary">
        <h2>Nombre del rol</h2>
        <form method="POST" action="{{ route('roles.update', $rol->id) }}" class="maintenance-management">@csrf @method('PUT')
            <div class="field"><label for="nombre">Nombre</label>
                <input id="nombre" name="nombre" maxlength="100" value="{{ old('nombre', $rol->name) }}" required>
                @error('nombre')<small class="field-error">{{ $message }}</small>@enderror
            </div>
            <button class="button primary">Guardar cambios</button>
        </form>
        <h2>Permisos del rol</h2>
        <form method="POST" action="{{ route('roles.permisos.update', $rol->id) }}">@csrf @method('PUT')
            @foreach($permisosPorModulo as $modulo => $permisos)
            <fieldset class="field"><legend><b>{{ ucfirst($modulo) }}</b></legend>
                @foreach($permisos as $permiso)
                <label><input type="checkbox" name="permisos[]" value="{{ $permiso->name }}" @checked(in_array($permiso->name, old('permisos', $seleccionados)))> {{ $permiso->name }}</label>
                @endforeach
            </fieldset>
            @endforeach
            @error('permisos')<small class="field-error">{{ $message }}</small>@enderror
            <p class="section-help">La lista se reemplaza completa. Solo puedes conceder permisos que ya posees.</p>
            <button class="button primary">Actualizar permisos</button>
        </form>
    </section>
    <section class="panel maintenance-management">
        <h2>Alcance y uso</h2>
        <p class="maintenance-help">Alcance: {{ $rol->copropiedad_id === null ? 'Global (todas las Copropiedades)' : 'Copropiedad '.$rol->copropiedad_id }}. Asignaciones activas: {{ $asignaciones }}.</p>
        @error('rol')<small class="field-error">{{ $message }}</small>@enderror
        <h2>Eliminar rol</h2>
        <p class="maintenance-help">Solo puede eliminarse cuando no está asignado a usuarios.</p>
        <form method="POST" action="{{ route('roles.destroy', $rol->id) }}" data-confirm="Eliminar rol" data-confirm-message="Esta acción eliminará el rol del catálogo de forma permanente.">@csrf @method('DELETE')
            <button class="button danger">Eliminar rol</button>
        </form>
    </section>
</div>
@endsection
