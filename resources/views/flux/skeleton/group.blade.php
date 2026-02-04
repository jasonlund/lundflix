@blaze

@props([
    'animate' => null,
])

<div
    {{ $attributes->class('flex flex-col gap-2') }}
    data-flux-skeleton-group
>
    {{ $slot }}
</div>
