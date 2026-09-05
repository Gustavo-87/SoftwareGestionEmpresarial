@props([
    'label',
    'variant' => 'status',
    'color' => null,
    'icon' => false,
])

@php
    $baseClass = match($variant) {
        'priority' => 'priority-badge',
        'role' => 'role-badge',
        default => 'status',
    };
    
    $colorClass = $color ? ' ' . $color : '';
@endphp

<span {{ $attributes->merge(['class' => $baseClass . $colorClass]) }}>
    @if($icon && $variant === 'status')
        <i aria-hidden="true"></i>
    @endif
    {{ $label }}
</span>
