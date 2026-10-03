@extends(auth()->user()->esAdministradorSistema() ? 'layouts.admin' : 'layouts.app')
@section('titulo', 'Roles y permisos')
@section('titulo_pagina', 'Roles y permisos')
@section('contenido')
<x-page-heading title="Roles y permisos" eyebrow="Administración de acceso" description="Roles que puedes administrar en tu alcance. Los permisos se organizan por módulo.">
    <x-slot name="actions"><a class="button primary" href="{{ route('roles.create') }}">Nuevo rol</a></x-slot>
</x-page-heading>
<section class="panel roles-catalog"><div class="table-wrap"><table><thead><tr><th>Rol</th><th>Alcance</th><th>Permisos</th><th>Asignaciones</th><th>Acciones</th></tr></thead><tbody>
@forelse($roles as $rol)
<tr>
    <td data-label="Rol"><strong>{{ $rol->name }}</strong></td>
    <td data-label="Alcance">{{ $rol->copropiedad_id === null ? 'Global' : 'Copropiedad '.$rol->copropiedad_id }}</td>
    <td data-label="Permisos">{{ $rol->permissions->count() }}</td>
    <td data-label="Asignaciones">{{ $rol->asignaciones }}</td>
    <td data-label="Acciones"><div class="user-actions">
        <a class="button subtle" href="{{ route('roles.edit', $rol->id) }}">Editar</a>
        <form method="POST" action="{{ route('roles.destroy', $rol->id) }}" data-confirm="Eliminar rol" data-confirm-message="El rol se eliminará del catálogo si no está asignado a usuarios.">@csrf @method('DELETE')<button class="button danger" type="submit">Eliminar</button></form>
    </div></td>
</tr>
@empty
<tr><td colspan="5"><x-empty-state title="No hay roles administrables" description="Los roles de tu alcance aparecerán aquí." /></td></tr>
@endforelse
</tbody></table></div></section>
@endsection
