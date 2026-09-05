<!DOCTYPE html>
@php($brandFallback = asset('logo-resuelve.png'))
@php($brandLogo = $siteSettings->logo_path ? Storage::url($siteSettings->logo_path) : $brandFallback)
@php($logoTransform = 'scale(' . ($siteSettings->logo_scale ?? 1) . ') translate(' . ($siteSettings->logo_offset_x ?? 0) . 'px, ' . ($siteSettings->logo_offset_y ?? 0) . 'px)')
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#12382f">
    <title>@yield('titulo', 'Admin') · Resuelve Admin</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="app-shell">
        <header class="top-navbar" id="navbar" aria-label="Menú de administración">
            <div class="top-navbar-inner">
                <a class="brand" href="{{ route('admin.index') }}" aria-label="Administración de Resuelve">
                    <span class="brand-mark image-mark"><img class="brand-logo-image" src="{{ $brandLogo }}" data-brand-fallback="{{ $brandFallback }}" onerror="this.onerror=null;this.src=this.dataset.brandFallback" alt="Logo de Resuelve" style="transform:{{ $logoTransform }}"></span>
                    <span><strong>Resuelve Admin</strong></span>
                </a>
                <button class="nav-toggle" id="navToggle" type="button" aria-label="Abrir menú" aria-haspopup="true" aria-expanded="false">Menú</button>
                <nav class="main-nav" id="mainNav" aria-label="Navegación de administración">
                    <a class="nav-item {{ request()->routeIs('admin.index') ? 'active' : '' }}" @if(request()->routeIs('admin.index')) aria-current="page" @endif href="{{ route('admin.index') }}">Inicio</a>
                    <a class="nav-item {{ request()->routeIs('admin.organizaciones.*') ? 'active' : '' }}" @if(request()->routeIs('admin.organizaciones.*')) aria-current="page" @endif href="{{ route('admin.organizaciones.index') }}">Organizaciones</a>
                    <a class="nav-item {{ request()->routeIs('admin.copropiedades.*') ? 'active' : '' }}" @if(request()->routeIs('admin.copropiedades.*')) aria-current="page" @endif href="{{ route('admin.copropiedades.index') }}">Copropiedades</a>
                    <a class="nav-item {{ request()->routeIs('admin.membresias.*') ? 'active' : '' }}" @if(request()->routeIs('admin.membresias.*')) aria-current="page" @endif href="{{ route('admin.membresias.index') }}">Membresías</a>
                    <a class="nav-item {{ request()->routeIs('admin.usuarios-globales.*') ? 'active' : '' }}" @if(request()->routeIs('admin.usuarios-globales.*')) aria-current="page" @endif href="{{ route('admin.usuarios-globales.index') }}">Usuarios</a>
                    <a class="nav-item {{ request()->routeIs('management.audit') ? 'active' : '' }}" @if(request()->routeIs('management.audit')) aria-current="page" @endif href="{{ route('management.audit') }}">Auditoría</a>
                    <a class="nav-item {{ request()->routeIs('settings.*') ? 'active' : '' }}" @if(request()->routeIs('settings.*')) aria-current="page" @endif href="{{ route('settings.edit') }}">Configuración</a>
                </nav>
                <div class="navbar-actions">
                    <button class="theme-toggle" id="themeToggle" type="button" aria-label="Activar modo oscuro"><span class="sun">☀</span><span class="moon">☾</span></button>
                    <span class="avatar navbar-avatar" title="{{ auth()->user()->name }}">{{ Str::upper(Str::substr(auth()->user()->name, 0, 2)) }}</span>
                    <form method="POST" action="{{ route('logout') }}" class="logout-form">@csrf<button class="logout-text" type="submit">Cerrar sesión</button></form>
                </div>
            </div>
        </header>

        <main class="main-content">
            <div class="page-content">
                @if(session('success'))
                    <x-notice variant="success">{{ session('success') }}</x-notice>
                @endif

                @yield('contenido')
            </div>
        </main>
    </div>
</body>
</html>
