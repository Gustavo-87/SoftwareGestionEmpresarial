@extends('auth.partials.shell')

@section('title', 'Selecciona tu contexto de acceso')
@section('eyebrow', $paso === 'copropiedad' ? 'Paso 1 de 2 · Copropiedad' : 'Paso 2 de 2 · Rol activo')
@section('headline', $paso === 'copropiedad' ? 'Elige dónde vas a trabajar.' : 'Elige con qué rol vas a trabajar.')
@section('message', $paso === 'copropiedad' ? 'Tu cuenta tiene membresía en varias Copropiedades. Selecciona una para continuar.' : 'Tu cuenta tiene varios roles autorizados. Selecciona uno para continuar.')

@section('form')
    <span class="eyebrow">Acceso seguro</span>
    <h2>{{ $paso === 'copropiedad' ? 'Selecciona la Copropiedad' : 'Selecciona el Rol activo' }}</h2>
    <p>{{ $paso === 'copropiedad' ? 'Podrás cambiar de Copropiedad más adelante desde el menú.' : 'Podrás cambiar de rol más adelante desde el menú.' }}</p>

    <form method="POST" action="{{ $paso === 'copropiedad' ? route('acceso.copropiedad') : route('acceso.rol') }}" class="login-form acceso-form">
        @csrf
        <fieldset class="acceso-opciones">
            <legend class="sr-only">{{ $paso === 'copropiedad' ? 'Copropiedades disponibles' : 'Roles disponibles' }}</legend>
            @if($paso === 'copropiedad')
                @foreach($membresias as $membresia)
                    <label class="acceso-opcion">
                        <input type="radio" name="copropiedad_id" value="{{ $membresia->copropiedad_id }}" required>
                        <span><strong>{{ $membresia->copropiedad->nombre }}</strong><small>Acceso vigente</small></span>
                    </label>
                @endforeach
            @else
                @php($membresiaActual = $membresias->first())
                @foreach($roles as $rol)
                    <label class="acceso-opcion">
                        <input type="radio" name="rol_id" value="{{ $rol->id }}" required @checked((string) request()->old('rol_id', $membresiaActual->rol_activo_id ?? '') === (string) $rol->id)>
                        <span><strong>{{ $rol->name }}</strong><small>{{ ((string) ($membresiaActual->rol_activo_id ?? '')) === (string) $rol->id ? 'Rol activo anterior' : 'Rol autorizado' }}</small></span>
                    </label>
                @endforeach
            @endif
        </fieldset>
        @error($paso === 'copropiedad' ? 'copropiedad_id' : 'rol_id')<small class="field-error">{{ $message }}</small>@enderror
        <button type="submit" class="button primary login-button">Continuar <span>→</span></button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="acceso-cancelar">@csrf<button class="button subtle" type="submit">Cerrar sesión</button></form>
@endsection
