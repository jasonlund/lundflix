@blaze

@php
    $classes = Flux::classes()
        ->add('p-[.3125rem]')
        ->add('overflow-y-auto')
        ->add('bg-zinc-700');
@endphp

<ui-options {{ $attributes->class($classes) }} data-flux-command-items>
    {{ $slot }}

    @if (isset($empty))
        <flux:command.empty>{!! $empty !!}</flux:command.empty>
    @else
        <flux:command.empty>{{ __('lundbergh.empty.search_no_results') }}</flux:command.empty>
    @endif
</ui-options>
