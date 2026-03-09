@props(['variant' => null])

@php
    $bubbleVariant = $variant === 'danger' ? 'error' : 'info';
@endphp

<x-lundbergh-bubble :variant="$bubbleVariant" :with-margin="false" bubbleClass="flex-1" contentTag="div">
    <flux:callout :$variant {{ $attributes }}>
        {{ $slot }}
    </flux:callout>
</x-lundbergh-bubble>
