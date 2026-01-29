@props([
    'container' => null,
    'padding' => true,
])

@php
    $classes = Flux::classes('[grid-area:main]')
        ->add($padding ? 'p-6 lg:p-8' : '')
        ->add($padding ? '[[data-flux-container]_&]:px-0' : '')
        ->add($container ? 'mx-auto w-full [:where(&)]:max-w-7xl' : '');
@endphp

<div {{ $attributes->class($classes) }} data-flux-main>
    {{ $slot }}
</div>
