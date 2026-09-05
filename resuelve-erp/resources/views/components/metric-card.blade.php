@props([
    'label',
    'value',
    'note' => null,
    'icon' => null,
    'variant' => 'neutral',
    'href' => null,
])

@php
    $variantClasses = [
        'danger' => 'metric-card-danger',
        'warning' => 'metric-card-warning',
        'success' => 'metric-card-success',
        'info' => 'metric-card-info',
        'cyan' => 'metric-card-cyan',
        'accent' => 'metric-card-accent',
        'neutral' => '',
    ];
    $variantClass = $variantClasses[$variant] ?? '';
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => 'metric-card ' . $variantClass]) }} aria-label="{{ $label }}: {{ $value }}">
        <div>
            <small>{{ $label }}</small>
            <strong>{{ $value }}</strong>
            @if($note)
                <p class="metric-note">{{ $note }}</p>
            @endif
        </div>
    </a>
@else
    <div {{ $attributes->merge(['class' => 'metric-card ' . $variantClass]) }} aria-label="{{ $label }}: {{ $value }}">
        <div>
            <small>{{ $label }}</small>
            <strong>{{ $value }}</strong>
            @if($note)
                <p class="metric-note">{{ $note }}</p>
            @endif
        </div>
    </div>
@endif
