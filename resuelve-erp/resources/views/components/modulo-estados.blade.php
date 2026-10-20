@props(['estados' => [], 'total' => 0, 'vacio' => 'Sin registros en este contexto.'])

<div class="modulo-estados">
    @if($total > 0)
        @foreach($estados as $estado)
            <div class="modulo-estado-row">
                <span class="modulo-estado-label">{{ $estado['label'] }}</span>
                <span class="modulo-estado-track" aria-hidden="true"><span class="modulo-estado-fill" style="width:{{ $estado['porcentaje'] }}%;background:{{ $estado['color'] }}"></span></span>
                <span class="modulo-estado-cantidad">{{ $estado['cantidad'] }}</span>
            </div>
        @endforeach
    @else
        <p class="modulo-estado-vacio">{{ $vacio }}</p>
    @endif
</div>
