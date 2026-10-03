@extends(auth()->user()->esAdministradorSistema() ? 'layouts.admin' : 'layouts.app')
@section('titulo', 'Nuevo rol')
@section('titulo_pagina', 'Nuevo rol')
@section('contenido')
<x-page-heading title="Nuevo rol" eyebrow="Administración de acceso" />
<a class="back-link" href="{{ route('roles.index') }}">Volver a Roles y permisos</a>
<section class="panel role-section role-create">
    <form class="role-create-form" method="POST" action="{{ route('roles.store') }}">@csrf
        <div class="field"><label for="nombre">Nombre del rol</label>
            <input id="nombre" name="nombre" maxlength="100" value="{{ old('nombre') }}" required>
            @error('nombre')<small class="field-error">{{ $message }}</small>@enderror
        </div>
        <div class="field"><label for="copiar_desde_rol_id">Copiar permisos desde</label>
            <select id="copiar_desde_rol_id" name="copiar_desde_rol_id">
                <option value="">Sin copiar</option>
                @foreach($origenes as $origen)
                <option value="{{ $origen->id }}" @selected(old('copiar_desde_rol_id') == $origen->id)>{{ $origen->name }} — {{ $origen->copropiedad_id === null ? 'Global' : 'Copropiedad '.$origen->copropiedad_id }}</option>
                @endforeach
            </select>
            <small class="field-help">Opcional. Parte de la matriz de permisos de un rol existente y podrás ajustarla.</small>
            @error('copiar_desde_rol_id')<small class="field-error">{{ $message }}</small>@enderror
        </div>
        <section aria-labelledby="role-permissions-title">
            <h2 id="role-permissions-title">Permisos por módulo</h2>
            @include('roles.partials.permisos')
        </section>
        <p class="role-help">Solo puedes conceder permisos que ya posees. La autoridad de plataforma no tiene esta restricción.</p>
        <button class="button primary">Crear rol</button>
    </form>
</section>
@endsection
