@blaze

@php
$classes = Flux::classes()
    ->add('p-0')
    ->add('overflow-y-auto')
    ->add('bg-transparent')
    ;
@endphp

<ui-options {{ $attributes->class($classes) }} data-flux-command-items>
    {{ $slot }}

    <flux:command.empty>{!! __('No results found') !!}</flux:command.empty>
</ui-options>
