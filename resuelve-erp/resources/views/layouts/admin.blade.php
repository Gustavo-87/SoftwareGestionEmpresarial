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
        <?php
            $navItems = [
                ['label' => 'Inicio', 'route' => 'admin.index', 'match' => 'admin.index', 'icon' => 'home'],
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
        ?>
        @include('partials.top-navbar', [
            'headerAria' => 'Menú de administración',
            'brandHref' => route('admin.index'),
            'brandLabel' => 'Resuelve Admin',
            'brandAria' => 'Administración de Resuelve',
            'logoAlt' => 'Logo de Resuelve',
            'navAria' => 'Navegación de administración',
            'navItems' => $navItems,
        ])

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
