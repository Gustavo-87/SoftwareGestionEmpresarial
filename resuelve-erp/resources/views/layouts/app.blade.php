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
        <header class="top-navbar" id="navbar" aria-label="Menú de Resuelve">
            <div class="top-navbar-inner">
                <a class="brand" href="{{ route('panel') }}" aria-label="Inicio de Resuelve">
                    <span class="brand-mark image-mark"><img class="brand-logo-image" src="{{ $brandLogo }}" data-brand-fallback="{{ $brandFallback }}" onerror="this.onerror=null;this.src=this.dataset.brandFallback" alt="Logo institucional de {{ $siteSettings->nombre_conjunto }}" style="transform:{{ $logoTransform }}"></span>
                    <span><strong>Resuelve</strong></span>
                </a>
                <button class="nav-toggle" id="navToggle" type="button" aria-label="Abrir menú" aria-haspopup="true" aria-expanded="false">Menú</button>
                <nav class="main-nav" id="mainNav" aria-label="Navegación principal">
                    <a class="nav-item {{ request()->routeIs('panel') ? 'active' : '' }}" @if(request()->routeIs('panel')) aria-current="page" @endif href="{{ route('panel') }}">Inicio</a>
                    @if(($navegacion['pqrs'] ?? false) || ($navegacion['crearPqrs'] ?? false))
                        <div class="nav-dropdown">
                            <button class="nav-item nav-dropdown-trigger" type="button" aria-haspopup="true" aria-expanded="false">PQRS <span class="nav-chevron" aria-hidden="true">▾</span></button>
                            <div class="nav-dropdown-menu" role="menu" aria-label="Submenú de PQRS">
                                @if($navegacion['pqrs'] ?? false)<a class="nav-dropdown-item {{ request()->routeIs('pqrs.index', 'pqrs.show', 'pqrs.edit') ? 'active' : '' }}" @if(request()->routeIs('pqrs.index', 'pqrs.show', 'pqrs.edit')) aria-current="page" @endif href="{{ route('pqrs.index') }}" role="menuitem">Listado</a>@endif
                                @if($navegacion['crearPqrs'] ?? false)<a class="nav-dropdown-item {{ request()->routeIs('pqrs.create') ? 'active' : '' }}" @if(request()->routeIs('pqrs.create')) aria-current="page" @endif href="{{ route('pqrs.create') }}" role="menuitem">Radicar</a>@endif
                            </div>
                        </div>
                    @endif
                    <div class="nav-dropdown">
                        <button class="nav-item nav-dropdown-trigger" type="button" aria-haspopup="true" aria-expanded="false">Personas <span class="nav-chevron" aria-hidden="true">▾</span></button>
                        <div class="nav-dropdown-menu" role="menu" aria-label="Submenú de Personas">
                            @if($navegacion['residentes'] ?? false)<a class="nav-dropdown-item {{ request()->routeIs('management.residents') ? 'active' : '' }}" @if(request()->routeIs('management.residents')) aria-current="page" @endif href="{{ route('management.residents') }}" role="menuitem">Residentes</a>@endif
                            <a class="nav-dropdown-item {{ request()->routeIs('profile.*') ? 'active' : '' }}" @if(request()->routeIs('profile.*')) aria-current="page" @endif href="{{ route('profile.edit') }}" role="menuitem">Mi perfil</a>
                        </div>
                    </div>
                    @if($navegacion['documentos'] ?? false)
                        <a class="nav-item {{ request()->routeIs('documentos.*') ? 'active' : '' }}" @if(request()->routeIs('documentos.*')) aria-current="page" @endif href="{{ route('documentos.index') }}">Documentos</a>
                    @endif
                    @if(($navegacion['herramientas'] ?? false) || ($navegacion['carga'] ?? false) || ($navegacion['auditoria'] ?? false) || ($navegacion['configuracion'] ?? false) || ($navegacion['usuarios'] ?? false) || ($navegacion['esAdministradorSistema'] ?? false))
                        <div class="nav-dropdown">
                            <button class="nav-item nav-dropdown-trigger" type="button" aria-haspopup="true" aria-expanded="false">Administración <span class="nav-chevron" aria-hidden="true">▾</span></button>
                            <div class="nav-dropdown-menu" role="menu" aria-label="Submenú de Administración">
                                @if($navegacion['carga'] ?? false)<a class="nav-dropdown-item {{ request()->routeIs('management.workload') ? 'active' : '' }}" href="{{ route('management.workload') }}" role="menuitem">Carga del equipo</a>@endif
                                @if($navegacion['herramientas'] ?? false)<a class="nav-dropdown-item {{ request()->routeIs('management.tools') ? 'active' : '' }}" href="{{ route('management.tools') }}" role="menuitem">Herramientas</a>@endif
                                @if($navegacion['auditoria'] ?? false)<a class="nav-dropdown-item {{ request()->routeIs('management.audit') ? 'active' : '' }}" href="{{ route('management.audit') }}" role="menuitem">Auditoría</a>@endif
                                @if($navegacion['configuracion'] ?? false)<a class="nav-dropdown-item {{ request()->routeIs('settings.*') ? 'active' : '' }}" href="{{ route('settings.edit') }}" role="menuitem">Configuración general</a>@endif
                                @if($navegacion['usuarios'] ?? false)<a class="nav-dropdown-item {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}" role="menuitem">Usuarios y roles</a>@endif
                                @if($navegacion['esAdministradorSistema'] ?? false)<a class="nav-dropdown-item {{ request()->routeIs('admin.*') ? 'active' : '' }}" @if(request()->routeIs('admin.*')) aria-current="page" @endif href="{{ route('admin.index') }}" role="menuitem">Administración del sistema</a>@endif
                            </div>
                        </div>
                    @endif
                </nav>
                <div class="navbar-context">
                    <span class="navbar-context-label">{{ $navegacion['copropiedad']->nombre ?? $siteSettings->nombre_conjunto }}</span>
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
                <div class="navbar-actions">
                    @if($navegacion['notificaciones'] ?? false)<a class="notification-button" href="{{ route('notifications.index') }}" aria-label="Notificaciones"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 6-3 7-3 9h18c0-2-3-3-3-9ZM10 21h4"/></svg>@if($unreadNotificationsCount)<b>{{ $unreadNotificationsCount }}</b>@endif</a>@endif
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

        <footer class="app-footer">
            <span>
                © {{ now()->year }} {{ config('app.name') }} · {{ $siteSettings->organizacion?->nombre ?? 'Organización' }}
            </span>

            <span class="app-footer-version">
                v1.0
            </span>
        </footer>
    </div>
    <dialog class="confirm-dialog" id="confirmDialog"><div><span class="confirm-icon">!</span><h2 id="confirmTitle">Confirmar acción</h2><p id="confirmMessage">¿Deseas continuar?</p><div><button class="button ghost" id="confirmCancel" type="button">Cancelar</button><button class="button danger" id="confirmAccept" type="button">Confirmar</button></div></div></dialog>
</body>
</html>
