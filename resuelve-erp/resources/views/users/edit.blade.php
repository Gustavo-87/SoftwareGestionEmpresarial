@extends('layouts.app')
@section('titulo','Editar usuario')
@section('titulo_pagina','Editar usuario')
@section('contenido')
<a class="back-link" href="{{ route('users.index') }}">← Volver a usuarios</a>
<section class="page-heading compact"><div><span class="eyebrow">Administración de acceso</span><h1>{{ $user->name }}</h1><p>Actualiza sus datos personales, unidad residencial, credenciales y permisos.</p></div></section>
<section class="form-panel"><div class="form-intro"><span class="form-step">01</span><div><h2>Información de la cuenta</h2><p>La nueva contraseña es opcional; déjala vacía para conservar la actual.</p></div></div>
<form method="POST" action="{{ route('users.update',$user) }}">@csrf @method('PUT')<div class="form-grid">
<div class="field"><label for="edit-name">Nombre completo</label><input id="edit-name" name="name" value="{{ old('name',$user->name) }}" required>@error('name')<span class="field-error">{{ $message }}</span>@enderror</div>
<div class="field"><label for="edit-email">Correo electrónico</label><input id="edit-email" name="email" type="email" value="{{ old('email',$user->email) }}" required>@error('email')<span class="field-error">{{ $message }}</span>@enderror</div>
<div class="field"><label for="edit-role">Rol</label><select id="edit-role" name="role" required><option value="admin" @selected(old('role',$user->role)==='admin')>Administrador</option><option value="gestor" @selected(old('role',$user->role)==='gestor')>Gestor</option><option value="apoyo" @selected(old('role',$user->role)==='apoyo')>Apoyo</option><option value="auditor" @selected(old('role',$user->role)==='auditor')>Auditor</option><option value="residente" @selected(old('role',$user->role)==='residente')>Residente</option></select></div>
<div class="field"><label for="edit-tower">Torre o bloque</label><input id="edit-tower" name="tower" value="{{ old('tower',$user->tower) }}"></div><div class="field"><label for="edit-unit">Apartamento o unidad</label><input id="edit-unit" name="unit" value="{{ old('unit',$user->unit) }}"></div>
<div class="field"><label for="edit-password">Nueva contraseña</label><input id="edit-password" name="password" type="password" minlength="8" autocomplete="new-password">@error('password')<span class="field-error">{{ $message }}</span>@enderror</div><div class="field"><label for="edit-password-confirmation">Confirmar nueva contraseña</label><input id="edit-password-confirmation" name="password_confirmation" type="password" minlength="8" autocomplete="new-password"></div>
</div><div class="form-actions"><div><a class="button ghost" href="{{ route('users.index') }}">Cancelar</a><button class="button primary" type="submit">Guardar cambios</button></div></div></form></section>
@endsection
