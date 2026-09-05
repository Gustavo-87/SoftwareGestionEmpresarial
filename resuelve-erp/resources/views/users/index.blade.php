@extends('layouts.app')
@section('titulo','Usuarios y roles')
@section('titulo_pagina','Usuarios y roles')
@section('contenido')
<x-page-heading
    title="Usuarios y permisos"
    eyebrow="Control de acceso"
>
    <x-slot name="actions">
        <button class="button primary" type="button" onclick="document.getElementById('createUser').toggleAttribute('open')"><span>＋</span> Nuevo usuario</button>
    </x-slot>
</x-page-heading>
<details class="user-create-panel" id="createUser" @if($errors->any()) open @endif>
    <summary>Crear una nueva cuenta</summary>
    <form method="POST" action="{{ route('users.store') }}">@csrf
        <div class="field"><label for="user-name">Nombre completo</label><input id="user-name" name="name" value="{{ old('name') }}" required maxlength="150">@error('name')<span class="field-error">{{ $message }}</span>@enderror</div>
        <div class="field"><label for="user-email">Correo electrónico</label><input id="user-email" name="email" type="email" value="{{ old('email') }}" required>@error('email')<span class="field-error">{{ $message }}</span>@enderror</div>
        <div class="field"><label for="user-role">Rol</label><select id="user-role" name="role" required><option value="residente" @selected(old('role','residente')==='residente')>Residente</option><option value="gestor" @selected(old('role')==='gestor')>Gestor</option><option value="apoyo" @selected(old('role')==='apoyo')>Apoyo</option><option value="auditor" @selected(old('role')==='auditor')>Auditor</option><option value="admin" @selected(old('role')==='admin')>Administrador</option></select></div>
        <div class="field"><label for="user-tower">Torre o bloque <small>(opcional)</small></label><input id="user-tower" name="tower" value="{{ old('tower') }}" maxlength="50"></div>
        <div class="field"><label for="user-unit">Apartamento o unidad <small>(opcional)</small></label><input id="user-unit" name="unit" value="{{ old('unit') }}" maxlength="50"></div>
        <div class="field"><label for="user-password">Contraseña temporal</label><input id="user-password" name="password" type="password" required minlength="8" autocomplete="new-password">@error('password')<span class="field-error">{{ $message }}</span>@enderror</div>
        <div class="field"><label for="user-password-confirmation">Confirmar contraseña</label><input id="user-password-confirmation" name="password_confirmation" type="password" required minlength="8" autocomplete="new-password"></div>
        <div class="user-create-actions"><button class="button primary" type="submit">Crear usuario</button></div>
    </form>
</details>
<section class="panel users-panel"><div class="role-guide"><span><b>Administrador</b> Control total</span><span><b>Gestor</b> Gestiona PQRS</span><span><b>Apoyo</b> Atiende asignadas</span><span><b>Auditor</b> Solo consulta</span><span><b>Residente</b> Sus solicitudes</span></div><div class="table-wrap"><table><thead><tr><th>Usuario</th><th>Correo</th><th>Rol actual</th><th>Acceso</th><th>Acciones</th></tr></thead><tbody>@foreach($users as $user)<tr><td><span class="owner"><span class="avatar small">{{ Str::upper(Str::substr($user->name,0,2)) }}</span>{{ $user->name }}</span></td><td>{{ $user->email }}</td><td><x-badge :label="ucfirst($user->role)" variant="role" color="{{ $user->role }}" /></td><td><form method="POST" action="{{ route('users.role.update',$user) }}" class="role-form" data-confirm="Cambiar rol de usuario" data-confirm-message="Este cambio modificará inmediatamente sus permisos.">@csrf @method('PATCH')<select name="role"><option value="admin" @selected($user->role==='admin')>Administrador</option><option value="gestor" @selected($user->role==='gestor')>Gestor</option><option value="apoyo" @selected($user->role==='apoyo')>Apoyo</option><option value="auditor" @selected($user->role==='auditor')>Auditor</option><option value="residente" @selected($user->role==='residente')>Residente</option></select><button class="button subtle">Actualizar</button></form></td><td><div class="user-actions"><a class="button subtle" href="{{ route('users.edit',$user) }}">Editar</a>@unless($user->is(auth()->user()))<form method="POST" action="{{ route('users.destroy',$user) }}">@csrf @method('DELETE')<button class="button danger" type="submit" data-confirm="Eliminar usuario" data-confirm-message="La cuenta se eliminará de forma permanente si no tiene PQRS asociadas.">Eliminar</button></form>@endunless</div></td></tr>@endforeach</tbody></table></div><div class="panel-footer">{{ $users->links() }}</div></section>
@endsection
