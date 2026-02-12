@blaze

@props([
    'placeholder' => null,
])

@php
    $classes = Flux::classes()
        ->add('w-full block overflow-hidden')
        ->add('rounded-none border-0 shadow-none');
@endphp

<ui-select
    clear="action"
    {{ $attributes->class($classes)->merge(['filter' => true]) }}
    data-flux-command
>
    {{ $slot }}
</ui-select>
