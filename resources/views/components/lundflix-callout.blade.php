@props(['variant' => null])

@php
    $bubbleVariant = $variant === 'danger' ? 'error' : 'info';
@endphp

<x-lundbergh-bubble
    :variant="$bubbleVariant"
    :with-margin="false"
    bubbleClass="flex-1"
    contentTag="div"
    :class="$attributes->get('class')"
>
    <flux:callout :$variant {{ $attributes->except('class') }}>
        {{ $slot }}
    </flux:callout>
</x-lundbergh-bubble>
