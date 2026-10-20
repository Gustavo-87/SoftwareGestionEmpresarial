@props(['estados' => [], 'total' => 0, 'vacio' => 'Sin registros en este contexto.'])

<div class="stats-donut-container">
    @if($total > 0)
        @php
            $gradosAcumulados = 0;
        @endphp
        <div class="stats-donut" style="background:conic-gradient(
            @foreach($estados as $i => $estado)
                {{ $estado['color'] }} {{ $gradosAcumulados }}deg {{ $gradosAcumulados + ($estado['porcentaje'] * 3.6) }}deg{{ $i < count($estados) - 1 ? ',' : '' }}
                @php $gradosAcumulados += $estado['porcentaje'] * 3.6; @endphp
            @endforeach
        )">
            <div class="stats-donut-center">
                <strong>{{ $total }}</strong>
                <small>total</small>
            </div>
        </div>
    @else
        <div class="empty-state" style="padding:18px"><p>{{ $vacio }}</p></div>
    @endif
    <div class="stats-legend">
        @foreach($estados as $estado)
            <div class="stats-legend-item"><span class="stats-legend-dot" style="background:{{ $estado['color'] }}"></span><span class="stats-legend-label">{{ $estado['label'] }}</span><span class="stats-legend-value">{{ $estado['cantidad'] }}</span><span class="stats-legend-pct">{{ $estado['porcentaje'] }}%</span></div>
        @endforeach
    </div>
</div>
