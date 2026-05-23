@props([
    'href' => null,
    'wireKey' => null,
    'muted' => false,
    'navigate' => true,
])

@php
    $tag = $href ? 'a' : 'div';
    $rowClasses = collect([
        'flex items-start gap-3 border-t border-white/20 px-4 py-3 transition-colors first:border-t-0 sm:items-center',
        $href ? 'hover:bg-white/5' : null,
        $muted ? 'opacity-50' : null,
    ])
        ->filter()
        ->implode(' ');
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @if ($navigate) wire:navigate @endif @endif
    @if ($wireKey) wire:key="{{ $wireKey }}" @endif
    {{ $attributes->class([$rowClasses]) }}
>
    {{ $leading ?? '' }}

    <span class="min-w-0 flex-1 overflow-hidden font-medium text-white">
        {{ $slot }}
    </span>

    {{ $trailing ?? '' }}
</{{ $tag }}>
