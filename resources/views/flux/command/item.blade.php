@blaze

@php
    $iconVariant ??= $attributes->pluck('icon:variant');
@endphp

@props([
    'iconVariant' => 'outline',
    'icon' => null,
    'kbd' => null,
])

@php
    $classes = Flux::classes()
        ->add('w-full group/item data-hidden:hidden flex items-center focus:outline-hidden')
        ->add('text-start text-sm font-medium')
        ->add('text-white data-active:bg-zinc-600');
@endphp

<ui-option action {{ $attributes->class($classes) }} data-flux-command-item>
    <?php if ($icon): ?>

    <div class="relative">
        <?php if (is_string($icon) && $icon !== ''): ?>

        <flux:icon :$icon :variant="$iconVariant" class="me-2 size-6 text-zinc-400 group-data-active/item:text-white" />

        <?php else: ?>

        {{ $icon }}

        <?php endif; ?>
    </div>

    <?php endif; ?>

    {{ $slot }}

    <?php if ($kbd): ?>

    <div class="ms-auto inline-flex rounded-sm bg-white/10 px-1 py-0.5">
        <span class="text-xs font-medium text-zinc-300">{{ $kbd }}</span>
    </div>

    <?php endif; ?>
</ui-option>
