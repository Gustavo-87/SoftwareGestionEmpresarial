<!DOCTYPE html>
@php
    $brandFallback = asset('logo-resuelve.png');
    $brandLogo = $siteSettings->logo_path ? Storage::url($siteSettings->logo_path) : $brandFallback;
    $logoTransform = 'scale(' . ($siteSettings->logo_scale ?? 1) . ') translate(' . ($siteSettings->logo_offset_x ?? 0) . 'px, ' . ($siteSettings->logo_offset_y ?? 0) . 'px)';
@endphp
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1e3a5f">
    <title>@yield('titulo', 'Admin') · Resuelve Admin</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="app-shell">
        <header class="top-navbar admin-navigation" id="navbar" aria-label="Menú de administración">
            <div class="top-navbar-inner">
                <a class="brand" href="{{ route('admin.index') }}" aria-label="Administración de Resuelve">
                    <span class="brand-mark image-mark"><img class="brand-logo-image" src="{{ $brandLogo }}" data-brand-fallback="{{ $brandFallback }}" onerror="this.onerror=null;this.src=this.dataset.brandFallback" alt="Logo de Resuelve" style="transform:{{ $logoTransform }}"></span>
                    <span><strong>Resuelve Admin</strong></span>
                </a>
                <button class="nav-toggle" id="navToggle" type="button" aria-label="Abrir menú" aria-haspopup="true" aria-expanded="false">Menú</button>
                <nav class="main-nav" id="mainNav" aria-label="Navegación de administración">
                    <a class="nav-item {{ request()->routeIs('admin.index') ? 'active' : '' }}" @if(request()->routeIs('admin.index')) aria-current="page" @endif href="{{ route('admin.index') }}">
                        @include('admin.partials.navigation-icon', ['icon' => 'home'])<span>Inicio</span>
                    </a>
                    @php
                        $adminNavGroups = [
                            ['id' => 'organization', 'label' => 'Organización', 'icon' => 'building', 'items' => [
                                ['label' => 'Organizaciones', 'route' => 'admin.organizaciones.index', 'match' => 'admin.organizaciones.*', 'icon' => 'building'],
                                ['label' => 'Copropiedades', 'route' => 'admin.copropiedades.index', 'match' => 'admin.copropiedades.*', 'icon' => 'home'],
                                ['label' => 'Membresías', 'route' => 'admin.membresias.index', 'match' => 'admin.membresias.*', 'icon' => 'membership'],
                            ]],
                            ['id' => 'access', 'label' => 'Usuarios y acceso', 'icon' => 'users', 'items' => [
                                ['label' => 'Usuarios', 'route' => 'admin.usuarios-globales.index', 'match' => 'admin.usuarios-globales.*', 'icon' => 'users'],
                                ['label' => 'Roles y permisos', 'route' => 'roles.index', 'match' => 'roles.*', 'icon' => 'shield'],
                            ]],
                            ['id' => 'administration', 'label' => 'Administración', 'icon' => 'settings', 'items' => [
                                ['label' => 'Auditoría', 'route' => 'management.audit', 'match' => 'management.audit', 'icon' => 'audit'],
                                ['label' => 'Configuración', 'route' => 'settings.edit', 'match' => 'settings.*', 'icon' => 'settings'],
                                ['label' => 'Configuración PQRS', 'route' => 'admin.tipos-pqr.index', 'match' => 'admin.tipos-pqr.*', 'icon' => 'shield'],
                            ]],
                        ];
                    @endphp
                    @foreach($adminNavGroups as $group)
                        <div class="nav-dropdown">
                            <button class="nav-item nav-dropdown-trigger {{ request()->routeIs(...array_column($group['items'], 'match')) ? 'active' : '' }}" type="button" aria-expanded="false" aria-controls="admin-nav-{{ $group['id'] }}">
                                @include('admin.partials.navigation-icon', ['icon' => $group['icon']])
                                <span>{{ $group['label'] }}</span><span class="nav-chevron" aria-hidden="true">▾</span>
                            </button>
                            <div class="nav-dropdown-menu" id="admin-nav-{{ $group['id'] }}">
                                @foreach($group['items'] as $item)
                                    <a class="nav-dropdown-item {{ request()->routeIs($item['match']) ? 'active' : '' }}" @if(request()->routeIs($item['match'])) aria-current="page" @endif href="{{ route($item['route']) }}">
                                        @include('admin.partials.navigation-icon', ['icon' => $item['icon']])<span>{{ $item['label'] }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
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

        @include('partials.app-footer')
    </div>
</body>
</html>
