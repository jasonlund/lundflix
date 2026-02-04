@blaze

@aware(['animate' => null])

@props([
    'animate' => null,
])

@php
    $animationClasses = match ($animate) {
        'shimmer' => [
            'relative before:absolute before:inset-0 before:-translate-x-full',
            'isolate overflow-hidden',
            '[:where(&)]:[--flux-shimmer-color:white]',
            'dark:[:where(&)]:[--flux-shimmer-color:var(--color-zinc-900)]',
            'before:z-10 before:animate-[flux-shimmer_2s_infinite]',
            'before:bg-gradient-to-r before:from-transparent before:via-[var(--flux-shimmer-color)]/50 before:to-transparent dark:before:via-[var(--flux-shimmer-color)]/50',
        ],
        'pulse' => 'animate-pulse',
        default => '',
    };

    $containerClasses = Flux::classes()->add('w-full');

    $bubbleClasses = Flux::classes()
        ->add('flex-1 min-w-0')
        ->add('flex flex-col gap-2');

    $placeholderClasses = Flux::classes()
        ->add('h-4 w-full rounded-md bg-zinc-300/60 dark:bg-zinc-700/50')
        ->add($animationClasses);
@endphp

<x-lundbergh-bubble
    contentTag="div"
    bubbleClass="{{ $bubbleClasses }}"
    data-flux-skeleton
    data-flux-skeleton-bubble
    {{ $attributes->class($containerClasses) }}
>
    <?php if ($slot->isEmpty()) {
        $loadingMessages = [
            __('lundbergh.loading.skeleton'),
            __('lundbergh.loading.please_wait'),
            __('lundbergh.loading.fetching'),
        ];
        $randomMessage = $loadingMessages[array_rand($loadingMessages)];
    ?>

    <flux:skeleton.group
        class="relative isolate overflow-hidden before:absolute before:inset-0 before:-translate-x-full before:animate-[flux-shimmer_2s_infinite] before:bg-gradient-to-r before:from-transparent before:via-white/60 before:to-transparent before:opacity-70 dark:before:via-zinc-900/60"
        data-flux-skeleton-text
    >
        <flux:text class="text-zinc-400/80 dark:text-zinc-500/70">{{ $randomMessage }}</flux:text>
    </flux:skeleton.group>

    <?php } else { ?>

    {{ $slot }}

    <?php } ?>
</x-lundbergh-bubble>
