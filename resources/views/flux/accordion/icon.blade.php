@aware(['disabled'])

@props([
    'pointing' => 'down',
    'disabled' => null,
    'active' => false,
])

@php
    $classes = Flux::classes()
        ->add($active ? 'text-white' : 'text-zinc-400')
        ->add($disabled ? '' : 'group-hover/accordion-heading:text-white');
@endphp

<flux:icon :icon="'chevron-'.$pointing" variant="mini" aria-hidden="true" :attributes="$attributes->class($classes)" />
