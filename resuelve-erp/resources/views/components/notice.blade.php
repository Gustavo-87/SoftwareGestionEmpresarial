@props([
    'variant' => 'success',
    'title' => null,
])

<div {{ $attributes->merge(['class' => "notice $variant"]) }} role="{{ $variant === 'error' ? 'alert' : 'status' }}">
    <span aria-hidden="true">{{ $variant === 'error' ? '!' : '✓' }}</span>
    @if($title || $slot->isNotEmpty())
        <div>
            @if($title)
                <strong>{{ $title }}</strong>
            @endif
            {{ $slot }}
        </div>
    @endif
</div>
