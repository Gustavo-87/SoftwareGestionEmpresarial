        <footer class="app-footer">
            <span>
                © {{ now()->year }} {{ config('app.name') }} · {{ $siteSettings->organizacion?->nombre ?? 'Organización' }}
            </span>

            <span class="app-footer-version">
                v1.0
            </span>
        </footer>
