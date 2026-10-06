{{-- Navbar compartido por la consola operativa y el panel de administración. --}}
{{-- Solo cambian por contexto: textos de marca, menú y acciones opcionales. --}}
<header class="top-navbar admin-navigation" id="navbar" aria-label="{{ $headerAria }}">
    <div class="top-navbar-inner">
        <a class="brand" href="{{ $brandHref }}" aria-label="{{ $brandAria }}">
            <span class="brand-mark image-mark"><img class="brand-logo-image" src="{{ $brandLogo }}" data-brand-fallback="{{ $brandFallback }}" onerror="this.onerror=null;this.src=this.dataset.brandFallback" alt="{{ $logoAlt }}" style="transform:{{ $logoTransform }}"></span>
            <span><strong>{{ $brandLabel }}</strong></span>
        </a>
        <button class="nav-toggle" id="navToggle" type="button" aria-label="Abrir menú" aria-haspopup="true" aria-expanded="false">Menú</button>
        <nav class="main-nav" id="mainNav" aria-label="{{ $navAria }}">
            @foreach($navItems as $item)
                @if(empty($item['items']))
                    @php($activo = request()->routeIs(...\Illuminate\Support\Arr::wrap($item['match'])))
                    <a class="nav-item {{ $activo ? 'active' : '' }}" @if($activo) aria-current="page" @endif href="{{ route($item['route']) }}">
                        @include('admin.partials.navigation-icon', ['icon' => $item['icon'] ?? 'home'])<span>{{ $item['label'] }}</span>
                    </a>
                @else
                    <?php
                        $patronesGrupo = \Illuminate\Support\Arr::flatten(\Illuminate\Support\Arr::pluck($item['items'], 'match'));
                        $grupoActivo = request()->routeIs(...$patronesGrupo);
                        $grupoId = 'nav-' . ($item['id'] ?? \Illuminate\Support\Str::slug($item['label']));
                    ?>
                    <div class="nav-dropdown">
                        <button class="nav-item nav-dropdown-trigger {{ $grupoActivo ? 'active' : '' }}" type="button" aria-haspopup="true" aria-expanded="false" aria-controls="{{ $grupoId }}">
                            @include('admin.partials.navigation-icon', ['icon' => $item['icon'] ?? 'home'])
                            <span>{{ $item['label'] }}</span><span class="nav-chevron" aria-hidden="true">▾</span>
                        </button>
                        <div class="nav-dropdown-menu" id="{{ $grupoId }}" role="menu" aria-label="Submenú de {{ $item['label'] }}">
                            @foreach($item['items'] as $sub)
                                @php($subActivo = request()->routeIs(...\Illuminate\Support\Arr::wrap($sub['match'])))
                                <a class="nav-dropdown-item {{ $subActivo ? 'active' : '' }}" @if($subActivo) aria-current="page" @endif href="{{ route($sub['route']) }}" role="menuitem">
                                    @include('admin.partials.navigation-icon', ['icon' => $sub['icon'] ?? 'home'])<span>{{ $sub['label'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        </nav>
        <div class="navbar-actions">
            @if($showNotifications ?? false)<a class="notification-button" href="{{ route('notifications.index') }}" aria-label="Notificaciones"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 6-3 7-3 9h18c0-2-3-3-3-9ZM10 21h4"/></svg>@if($unreadNotificationsCount ?? 0)<b>{{ $unreadNotificationsCount }}</b>@endif</a>@endif
            <button class="theme-toggle" id="themeToggle" type="button" aria-label="Activar modo oscuro"><span class="sun">☀</span><span class="moon">☾</span></button>
            <span class="avatar navbar-avatar" title="{{ auth()->user()->name }}">{{ Str::upper(Str::substr(auth()->user()->name, 0, 2)) }}</span>
            <form method="POST" action="{{ route('logout') }}" class="logout-form">@csrf<button class="logout-text" type="submit">Cerrar sesión</button></form>
        </div>
    </div>
</header>
