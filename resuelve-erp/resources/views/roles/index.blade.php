@extends(auth()->user()->esAdministradorSistema() ? 'layouts.admin' : 'layouts.app')
@section('titulo', 'Roles y permisos')
@section('titulo_pagina', 'Roles y permisos')
@section('contenido')
<x-page-heading title="Roles y permisos" eyebrow="Administración de acceso">
    <x-slot name="actions"><a class="button primary" href="{{ route('roles.create') }}">Nuevo rol</a></x-slot>
</x-page-heading>
<p>Roles que puedes administrar en tu alcance. Los permisos se organizan por módulo.</p>
<section class="panel"><div class="table-wrap"><table><thead><tr><th>Rol</th><th>Alcance</th><th>Permisos</th><th>Asignaciones</th><th>Acciones</th></tr></thead><tbody>
@forelse($roles as $rol)
<tr>
    <td>{{ $rol->name }}</td>
    <td>{{ $rol->copropiedad_id === null ? 'Global' : 'Copropiedad '.$rol->copropiedad_id }}</td>
    <td>{{ $rol->permissions->count() }}</td>
    <td>{{ $rol->asignaciones }}</td>
    <td><div class="user-actions">
        <a class="button subtle" href="{{ route('roles.edit', $rol->id) }}">Editar</a>
        <form method="POST" action="{{ route('roles.destroy', $rol->id) }}" data-confirm="Eliminar rol" data-confirm-message="El rol se eliminará del catálogo si no está asignado a usuarios.">@csrf @method('DELETE')<button class="button danger" type="submit">Eliminar</button></form>
    </div></td>
</tr>
@empty
<tr><td colspan="5"><x-empty-state title="No hay roles administrables" description="Los roles de tu alcance aparecerán aquí." /></td></tr>
@endforelse
</tbody></table></div></section>
@endsection
