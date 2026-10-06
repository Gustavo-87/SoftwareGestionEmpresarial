<!DOCTYPE html>
@php($brandFallback = asset('logo-resuelve.png'))
@php($brandLogo = $siteSettings->logo_path ? Storage::url($siteSettings->logo_path) : $brandFallback)
@php($logoTransform = 'scale(' . ($siteSettings->logo_scale ?? 1) . ') translate(' . ($siteSettings->logo_offset_x ?? 0) . 'px, ' . ($siteSettings->logo_offset_y ?? 0) . 'px)')
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1e3a5f">
    <meta name="description" content="Plataforma para la gestión y seguimiento de peticiones, quejas, reclamos y sugerencias.">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $siteSettings->nombre_conjunto }} · Gestión de PQRS">
    <meta property="og:description" content="Gestión de PQRS clara y a tiempo.">
    <meta property="og:image" content="{{ url('/og.png') }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $siteSettings->nombre_conjunto }} · Gestión de PQRS">
    <meta name="twitter:description" content="Gestión de PQRS clara y a tiempo.">
    <meta name="twitter:image" content="{{ url('/og.png') }}">
    <link rel="icon" href="{{ $brandLogo }}?v={{ $siteSettings->updated_at?->timestamp ?? 1 }}">
    <link rel="apple-touch-icon" href="{{ $brandLogo }}?v={{ $siteSettings->updated_at?->timestamp ?? 1 }}">
    <title>@yield('titulo', 'Panel') · {{ $siteSettings->nombre_conjunto }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body style="--forest: #1e3a5f">
    <div class="app-shell">
        <?php
            $navItems = [
                ['label' => 'Inicio', 'route' => 'panel', 'match' => 'panel', 'icon' => 'home'],
            ];
            if (($navegacion['pqrs'] ?? false) || ($navegacion['crearPqrs'] ?? false)) {
                $pqrsItems = [];
                if ($navegacion['pqrs'] ?? false) {
                    $pqrsItems[] = ['label' => 'Listado', 'route' => 'pqrs.index', 'match' => ['pqrs.index', 'pqrs.show', 'pqrs.edit'], 'icon' => 'audit'];
                }
                if ($navegacion['crearPqrs'] ?? false) {
                    $pqrsItems[] = ['label' => 'Radicar', 'route' => 'pqrs.create', 'match' => 'pqrs.create', 'icon' => 'shield'];
                }
                $navItems[] = ['id' => 'pqrs', 'label' => 'PQRS', 'icon' => 'shield', 'items' => $pqrsItems];
            }
            if ($navegacion['mantenimiento'] ?? false) {
                $navItems[] = ['label' => 'Mantenimiento', 'route' => 'mantenimiento.index', 'match' => 'mantenimiento.*', 'icon' => 'settings'];
            }
            if ($navegacion['documentos'] ?? false) {
                $navItems[] = ['label' => 'Documentos', 'route' => 'documentos.index', 'match' => 'documentos.*', 'icon' => 'audit'];
            }
            if (($navegacion['herramientas'] ?? false) || ($navegacion['carga'] ?? false) || ($navegacion['auditoria'] ?? false) || ($navegacion['configuracion'] ?? false) || ($navegacion['usuarios'] ?? false) || ($navegacion['catalogoRoles'] ?? false) || ($navegacion['esAdministradorSistema'] ?? false)) {
                $adminItems = [];
                if ($navegacion['carga'] ?? false) {
                    $adminItems[] = ['label' => 'Carga del equipo', 'route' => 'management.workload', 'match' => 'management.workload', 'icon' => 'users'];
                }
                if ($navegacion['herramientas'] ?? false) {
                    $adminItems[] = ['label' => 'Herramientas', 'route' => 'management.tools', 'match' => 'management.tools', 'icon' => 'settings'];
                }
                if ($navegacion['auditoria'] ?? false) {
                    $adminItems[] = ['label' => 'Auditoría', 'route' => 'management.audit', 'match' => 'management.audit', 'icon' => 'audit'];
                }
                if ($navegacion['configuracion'] ?? false) {
                    $adminItems[] = ['label' => 'Configuración general', 'route' => 'settings.edit', 'match' => 'settings.*', 'icon' => 'settings'];
                }
                if ($navegacion['usuarios'] ?? false) {
                    $adminItems[] = ['label' => 'Usuarios y roles', 'route' => 'users.index', 'match' => 'users.*', 'icon' => 'users'];
                }
                if ($navegacion['catalogoRoles'] ?? false) {
                    $adminItems[] = ['label' => 'Catálogo de roles', 'route' => 'roles.index', 'match' => 'roles.*', 'icon' => 'shield'];
                }
                if ($navegacion['esAdministradorSistema'] ?? false) {
                    $adminItems[] = ['label' => 'Administración del sistema', 'route' => 'admin.index', 'match' => 'admin.*', 'icon' => 'building'];
                }
                $navItems[] = ['id' => 'administracion', 'label' => 'Administración', 'icon' => 'building', 'items' => $adminItems];
            }
            $personasItems = [];
            if ($navegacion['residentes'] ?? false) {
                $personasItems[] = ['label' => 'Residentes', 'route' => 'management.residents', 'match' => 'management.residents', 'icon' => 'users'];
            }
            $personasItems[] = ['label' => 'Mi perfil', 'route' => 'profile.edit', 'match' => 'profile.*', 'icon' => 'membership'];
            $navItems[] = ['id' => 'personas', 'label' => 'Perfil', 'icon' => 'users', 'items' => $personasItems];
        ?>
        @include('partials.top-navbar', [
            'headerAria' => 'Menú de Resuelve',
            'brandHref' => route('panel'),
            'brandLabel' => 'Resuelve',
            'brandAria' => 'Inicio de Resuelve',
            'logoAlt' => 'Logo institucional de ' . $siteSettings->nombre_conjunto,
            'navAria' => 'Navegación principal',
            'navItems' => $navItems,
            'showNotifications' => $navegacion['notificaciones'] ?? false,
        ])

        <main class="main-content">
            <div class="page-content">
                <div class="operational-context" aria-label="Copropiedad activa">
                    <span class="operational-context-label">{{ $navegacion['copropiedad']->nombre ?? $siteSettings->nombre_conjunto }}</span>
                    @if(count($copropiedadesDisponibles ?? []) > 1)
                        <form method="POST" action="{{ route('contexto.cambiar') }}" class="contexto-selector-form">
                            @csrf
                            <select id="copropiedad_selector" name="copropiedad_id" aria-label="Cambiar copropiedad activa" onchange="this.form.submit()">
                                @foreach($copropiedadesDisponibles as $copropiedad)
                                    <option value="{{ $copropiedad->id }}" {{ ($navegacion['copropiedad']->id ?? null) === $copropiedad->id ? 'selected' : '' }}>{{ $copropiedad->nombre }}</option>
                                @endforeach
                            </select>
                        </form>
                    @endif
                </div>
                @if(session('success'))
                    <x-notice variant="success">{{ session('success') }}</x-notice>
                @endif

                @yield('contenido')
            </div>
        </main>

        @include('partials.app-footer')
    </div>
    <dialog class="confirm-dialog" id="confirmDialog"><div><span class="confirm-icon">!</span><h2 id="confirmTitle">Confirmar acción</h2><p id="confirmMessage">¿Deseas continuar?</p><div><button class="button ghost" id="confirmCancel" type="button">Cancelar</button><button class="button danger" id="confirmAccept" type="button">Confirmar</button></div></div></dialog>
</body>
</html>
