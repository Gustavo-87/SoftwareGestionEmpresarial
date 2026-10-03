<svg class="admin-nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
    @switch($icon)
        @case('home') <path d="m3 10 9-7 9 7M5 9v12h14V9M9 21v-7h6v7"/> @break
        @case('building') <rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 21v-4h6v4M9 7h1m4 0h1M9 11h1m4 0h1"/> @break
        @case('membership') <rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M5.5 17c0-4 7-4 7 0M16 9h2m-2 4h2"/> @break
        @case('users') <circle cx="9" cy="8" r="3"/><path d="M3 21v-2a6 6 0 0 1 12 0v2M16 5a3 3 0 0 1 0 6m2 4a5 5 0 0 1 3 4v2"/> @break
        @case('shield') <path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3Z"/><path d="m8 12 3 3 5-6"/> @break
        @case('audit') <rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 7h6M9 11h6M9 15h4"/> @break
        @case('settings') <path d="M4 6h16M4 12h16M4 18h16"/><circle cx="8" cy="6" r="2"/><circle cx="16" cy="12" r="2"/><circle cx="10" cy="18" r="2"/> @break
    @endswitch
</svg>
