@extends('auth.partials.shell')
@section('title', 'Recuperar contraseña')
@section('eyebrow', 'Recuperación segura')
@section('headline', 'Vuelve a tu cuenta.')
@section('message', 'Te enviaremos un enlace seguro para crear una nueva contraseña.')
@section('form')
    <span class="eyebrow">Recuperar acceso</span><h2>¿Olvidaste tu contraseña?</h2><p>Escribe el correo asociado a tu perfil.</p>
    @if(session('success'))<x-notice variant="success">{{ session('success') }}</x-notice>@endif
    <form method="POST" action="{{ route('password.email') }}" class="login-form">@csrf
        <div class="field"><label for="email">Correo electrónico</label><input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus class="@error('email') invalid @enderror">@error('email')<small class="field-error">{{ $message }}</small>@enderror</div>
        <button class="button primary login-button" type="submit">Enviar enlace <span>→</span></button>
        <a class="auth-back" href="{{ route('login') }}">← Volver a iniciar sesión</a>
    </form>
@endsection
