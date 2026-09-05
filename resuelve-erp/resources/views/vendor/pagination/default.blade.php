@if ($paginator->hasPages())
    <nav class="pagination-wrapper" role="navigation" aria-label="Paginación">
        {{-- Resumen --}}
        <div class="pagination-summary">
            Mostrando {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} de {{ $paginator->total() }} registros
        </div>

        {{-- Controles --}}
        <div class="pagination-controls">
            {{-- Anterior --}}
            @if ($paginator->onFirstPage())
                <span class="pagination-item pagination-disabled" aria-disabled="true">
                    <span class="pagination-arrow">‹</span> Anterior
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="pagination-item pagination-link" rel="prev">
                    <span class="pagination-arrow">‹</span> Anterior
                </a>
            @endif

            {{-- Números de página --}}
            <div class="pagination-numbers">
                @foreach ($elements as $element)
                    {{-- "Three Dots" Separator --}}
                    @if (is_string($element))
                        <span class="pagination-dots">{{ $element }}</span>
                    @endif

                    {{-- Array Of Links --}}
                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span class="pagination-item pagination-current" aria-current="page">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="pagination-item pagination-link">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </div>

            {{-- Siguiente --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="pagination-item pagination-link" rel="next">
                    Siguiente <span class="pagination-arrow">›</span>
                </a>
            @else
                <span class="pagination-item pagination-disabled" aria-disabled="true">
                    Siguiente <span class="pagination-arrow">›</span>
                </span>
            @endif
        </div>
    </nav>
@endif
