@blaze

@php
    $iconTrailing ??= $attributes->pluck('icon:trailing');
@endphp

@php
    $iconVariant ??= $attributes->pluck('icon:variant');
@endphp

@props([
    'iconVariant' => 'micro',
    'iconTrailing' => null,
    'variant' => null,
    'color' => null,
    'inset' => null,
    'size' => null,
    'icon' => null,
])

@php
    $insetClasses = Flux::applyInset($inset, top: '-mt-1', right: '-me-2', bottom: '-mb-1', left: '-ms-2');

    // When using the outline icon variant, we need to size it down to match the default icon sizes...
    $iconClasses = Flux::classes()->add($iconVariant === 'outline' ? 'size-4' : '');

    $classes = Flux::classes()
        ->add('inline-flex items-center font-medium whitespace-nowrap')
        ->add($insetClasses)
        ->add('[print-color-adjust:exact]')
        ->add(
            match ($size) {
                'lg' => 'text-sm py-1.5 **:data-flux-badge-icon:me-2',
                default => 'text-sm py-1 **:data-flux-badge-icon:me-1.5',
                'sm' => 'text-xs py-1 **:data-flux-badge-icon:size-3 **:data-flux-badge-icon:me-1',
            },
        )
        ->add(
            match ($variant) {
                'pill' => 'rounded-full px-3',
                default => 'rounded-md px-2',
            },
        )
        /**
         * We can't compile classes for each color because of variants color to color and Tailwind's JIT compiler.
         * We instead need to write out each one by hand. Sorry...
         */
        ->add(
            $variant === 'solid'
                ? match ($color) {
                    default => 'text-white bg-zinc-600 [&:is(button)]:hover:bg-zinc-500',
                    'lundflix' => 'text-white bg-lundflix [&:is(button)]:hover:bg-lundflix/85',
                    'red' => 'text-white bg-red-600 [&:is(button)]:hover:bg-red-500',
                    'orange' => 'text-white bg-orange-600 [&:is(button)]:hover:bg-orange-500',
                    'amber' => 'text-white bg-amber-500 [&:is(button)]:hover:bg-amber-400',
                    'yellow' => 'text-zinc-950 bg-yellow-400 [&:is(button)]:hover:bg-yellow-300',
                    'lime' => 'text-white bg-lime-600 [&:is(button)]:hover:bg-lime-500',
                    'green' => 'text-white bg-green-600 [&:is(button)]:hover:bg-green-500',
                    'emerald' => 'text-white bg-emerald-600 [&:is(button)]:hover:bg-emerald-500',
                    'teal' => 'text-white bg-teal-600 [&:is(button)]:hover:bg-teal-500',
                    'cyan' => 'text-white bg-cyan-600 [&:is(button)]:hover:bg-cyan-500',
                    'sky' => 'text-white bg-sky-600 [&:is(button)]:hover:bg-sky-500',
                    'blue' => 'text-white bg-blue-600 [&:is(button)]:hover:bg-blue-500',
                    'indigo' => 'text-white bg-indigo-600 [&:is(button)]:hover:bg-indigo-500',
                    'violet' => 'text-white bg-violet-600 [&:is(button)]:hover:bg-violet-500',
                    'purple' => 'text-white bg-purple-600 [&:is(button)]:hover:bg-purple-500',
                    'fuchsia' => 'text-white bg-fuchsia-600 [&:is(button)]:hover:bg-fuchsia-500',
                    'pink' => 'text-white bg-pink-600 [&:is(button)]:hover:bg-pink-500',
                    'rose' => 'text-white bg-rose-600 [&:is(button)]:hover:bg-rose-500',
                }
                : // The ! (important) modifiers on [&_button] selectors are from Flux's original component source.
                // They're needed to override Flux's internal button text color when a button is nested inside a badge.
                match ($color) {
                    default => 'text-zinc-200 [&_button]:text-zinc-200! bg-zinc-400/40 [&:is(button)]:hover:bg-zinc-400/50',
                    'lundflix' => 'text-white bg-lundflix/75',
                    'red' => 'text-red-200 [&_button]:text-red-200! bg-red-400/40 [&:is(button)]:hover:bg-red-400/50',
                    'orange' => 'text-orange-200 [&_button]:text-orange-200! bg-orange-400/40 [&:is(button)]:hover:bg-orange-400/50',
                    'amber' => 'text-amber-200 [&_button]:text-amber-200! bg-amber-400/40 [&:is(button)]:hover:bg-amber-400/50',
                    'yellow' => 'text-yellow-200 [&_button]:text-yellow-200! bg-yellow-400/40 [&:is(button)]:hover:bg-yellow-400/50',
                    'lime' => 'text-lime-200 [&_button]:text-lime-200! bg-lime-400/40 [&:is(button)]:hover:bg-lime-400/50',
                    'green' => 'text-green-200 [&_button]:text-green-200! bg-green-400/40 [&:is(button)]:hover:bg-green-400/50',
                    'emerald' => 'text-emerald-200 [&_button]:text-emerald-200! bg-emerald-400/40 [&:is(button)]:hover:bg-emerald-400/50',
                    'teal' => 'text-teal-200 [&_button]:text-teal-200! bg-teal-400/40 [&:is(button)]:hover:bg-teal-400/50',
                    'cyan' => 'text-cyan-200 [&_button]:text-cyan-200! bg-cyan-400/40 [&:is(button)]:hover:bg-cyan-400/50',
                    'sky' => 'text-sky-200 [&_button]:text-sky-200! bg-sky-400/40 [&:is(button)]:hover:bg-sky-400/50',
                    'blue' => 'text-blue-200 [&_button]:text-blue-200! bg-blue-400/40 [&:is(button)]:hover:bg-blue-400/50',
                    'indigo' => 'text-indigo-200 [&_button]:text-indigo-200! bg-indigo-400/40 [&:is(button)]:hover:bg-indigo-400/50',
                    'violet' => 'text-violet-200 [&_button]:text-violet-200! bg-violet-400/40 [&:is(button)]:hover:bg-violet-400/50',
                    'purple' => 'text-purple-200 [&_button]:text-purple-200! bg-purple-400/40 [&:is(button)]:hover:bg-purple-400/50',
                    'fuchsia' => 'text-fuchsia-200 [&_button]:text-fuchsia-200! bg-fuchsia-400/40 [&:is(button)]:hover:bg-fuchsia-400/50',
                    'pink' => 'text-pink-200 [&_button]:text-pink-200! bg-pink-400/40 [&:is(button)]:hover:bg-pink-400/50',
                    'rose' => 'text-rose-200 [&_button]:text-rose-200! bg-rose-400/40 [&:is(button)]:hover:bg-rose-400/50',
                },
        );
@endphp

<flux:button-or-div :attributes="$attributes->class($classes)" data-flux-badge>
    <?php if (is_string($icon) && $icon !== '') { ?>

    <flux:icon :$icon :variant="$iconVariant" :class="$iconClasses" data-flux-badge-icon />

    <?php } else { ?>

    {{ $icon }}

    <?php } ?>

    {{ $slot }}

    <?php if ($iconTrailing) { ?>

    <div class="flex items-center ps-1" data-flux-badge-icon:trailing>
        <?php if (is_string($iconTrailing)) { ?>

        <flux:icon :icon="$iconTrailing" :variant="$iconVariant" :class="$iconClasses" />

        <?php } else { ?>

        {{ $iconTrailing }}

        <?php } ?>
    </div>

    <?php } ?>
</flux:button-or-div>
