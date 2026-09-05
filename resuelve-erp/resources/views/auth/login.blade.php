<!DOCTYPE html>
@php($brandFallback = asset('logo-resuelve.png'))
@php($organizationName = $siteSettings->organizacion?->nombre ?? 'Gestión empresarial')
@php($tabLogo = $siteSettings->logo_path ? Storage::url($siteSettings->logo_path) : $brandFallback)
@php($logoTransform = 'scale(' . ($siteSettings->logo_scale ?? 1) . ') translate(' . ($siteSettings->logo_offset_x ?? 0) . 'px, ' . ($siteSettings->logo_offset_y ?? 0) . 'px)')
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1e3a5f">
    <link rel="icon" href="{{ $tabLogo }}?v={{ $siteSettings->updated_at?->timestamp ?? 1 }}">
    <link rel="apple-touch-icon" href="{{ $tabLogo }}?v={{ $siteSettings->updated_at?->timestamp ?? 1 }}">
    <title>Resuelve ERP · {{ $organizationName }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-page" style="--forest: #1e3a5f">
    <main class="auth-shell">
        <section class="auth-brand-panel">
            <a class="brand auth-brand" href="/"><span class="brand-mark image-mark"><img class="brand-logo-image" src="{{ $tabLogo }}" data-brand-fallback="{{ $brandFallback }}" onerror="this.onerror=null;this.src=this.dataset.brandFallback" alt="Logo institucional de {{ $organizationName }}" style="transform:{{ $logoTransform }}"></span><span><strong>Resuelve ERP</strong><small>{{ $organizationName }}</small></span></a>
            <div class="auth-message">
                <h1>La gestión de tu organización, en un solo lugar.</h1>
                <p>Centraliza la administración de tus copropiedades, la atención de PQRS y la gestión documental en un mismo espacio de trabajo.</p>
            </div>
            <div class="auth-benefits"><span>✓ Operación contextual</span><span>✓ Información protegida</span><span>✓ Seguimiento trazable</span></div>
        </section>
        <section class="auth-form-panel">
            <div class="auth-form-wrap">
                <span class="eyebrow">Acceso seguro</span>
                <h2>Accede a Resuelve ERP</h2>
                <p>Ingresa con el correo asignado a tu perfil.</p>

                @if(session('success'))<x-notice variant="success">{{ session('success') }}</x-notice>@endif

                <form method="POST" action="{{ route('login.store') }}" class="login-form">
                    @csrf
                    <div class="field"><label for="email">Correo electrónico</label><input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="nombre@correo.com" autocomplete="username" required autofocus class="@error('email') invalid @enderror">@error('email')<small class="field-error">{{ $message }}</small>@enderror</div>
                    <div class="field"><label for="password">Contraseña</label><div class="password-field"><input id="password" name="password" type="password" autocomplete="current-password" required><button type="button" data-password-toggle aria-label="Mostrar contraseña" aria-pressed="false"><svg class="eye-open" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.7"/></svg><svg class="eye-closed" viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M10.6 6.1A9 9 0 0 1 12 6c6 0 9.5 6 9.5 6a17 17 0 0 1-2.1 2.7M6.2 6.2C3.8 7.8 2.5 12 2.5 12s3.5 6 9.5 6a9 9 0 0 0 3-.5"/></svg></button></div></div>
                    <div class="login-options"><label class="remember"><input type="checkbox" name="remember" value="1"> Mantener mi sesión iniciada</label><a href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a></div>
                    <button type="submit" class="button primary login-button">Iniciar sesión <span>→</span></button>
                </form>
                <p class="security-note"><span>⌾</span> Tus datos de acceso están protegidos.</p>
            </div>
        </section>
    </main>
</body>
</html>
