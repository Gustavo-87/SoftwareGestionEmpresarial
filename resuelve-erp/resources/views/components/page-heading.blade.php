@props([
    'title',
    'description' => null,
    'eyebrow' => null,
    'compact' => false,
])

<section {{ $attributes->merge(['class' => 'page-heading' . ($compact ? ' compact' : '')]) }}>
    <div>
        @if($eyebrow)
            <span class="eyebrow">{{ $eyebrow }}</span>
        @endif
        <h1>{{ $title }}</h1>
        @if($description)
            <p>{{ $description }}</p>
        @endif
    </div>
    @if(isset($actions))
        <div class="heading-actions">
            {{ $actions }}
        </div>
    @endif
</section>
