@extends('auth.partials.shell')
@section('title', 'Nueva contraseña')
@section('eyebrow', 'Acceso seguro')
@section('headline', 'Crea una nueva contraseña.')
@section('message', 'Usa al menos ocho caracteres y evita reutilizar claves de otros servicios.')
@section('form')
    <span class="eyebrow">Restablecer acceso</span><h2>Nueva contraseña</h2><p>Define la clave que usarás para ingresar.</p>
    <form method="POST" action="{{ route('password.update') }}" class="login-form">@csrf<input type="hidden" name="token" value="{{ $token }}">
        <div class="field"><label for="email">Correo electrónico</label><input id="email" name="email" type="email" value="{{ old('email', $email) }}" required autocomplete="email">@error('email')<small class="field-error">{{ $message }}</small>@enderror</div>
        <div class="field"><label for="password">Nueva contraseña</label><div class="password-field"><input id="password" name="password" type="password" required autocomplete="new-password">@include('auth.partials.password-toggle', ['target' => 'password'])</div>@error('password')<small class="field-error">{{ $message }}</small>@enderror</div>
        <div class="field"><label for="password_confirmation">Confirmar contraseña</label><div class="password-field"><input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">@include('auth.partials.password-toggle', ['target' => 'password_confirmation'])</div></div>
        <button class="button primary login-button" type="submit">Guardar contraseña <span>→</span></button>
    </form>
@endsection
