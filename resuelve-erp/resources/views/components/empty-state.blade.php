@props([
    'title',
    'description' => null,
    'icon' => null,
])

<div {{ $attributes->merge(['class' => 'empty-state']) }}>
    @if($icon)
        <span>{{ $icon }}</span>
    @else
        <span class="empty-illustration"><i></i><b>✓</b></span>
    @endif
    <h3>{{ $title }}</h3>
    @if($description)
        <p>{{ $description }}</p>
    @endif
    {{ $slot }}
</div>
