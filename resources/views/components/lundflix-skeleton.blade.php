@props(['animate' => null])

@php
    $loadingMessages = [
        __('lundbergh.loading.skeleton'),
        __('lundbergh.loading.please_wait'),
        __('lundbergh.loading.fetching'),
    ];
    $randomMessage = $loadingMessages[array_rand($loadingMessages)];
@endphp

<x-lundbergh-bubble
    contentTag="div"
    bubbleClass="flex-1 min-w-0 flex flex-col gap-2"
    data-flux-skeleton-bubble
    {{ $attributes }}
>
    <flux:skeleton :$animate>
        @if ($slot->isEmpty())
            <flux:skeleton.group
                class="relative isolate overflow-hidden before:absolute before:inset-0 before:-translate-x-full before:animate-[flux-shimmer_2s_infinite] before:bg-linear-to-r before:from-transparent before:via-zinc-900/60 before:to-transparent before:opacity-70"
            >
                <flux:text class="text-zinc-500/70">{{ $randomMessage }}</flux:text>
            </flux:skeleton.group>
        @else
            {{ $slot }}
        @endif
    </flux:skeleton>
</x-lundbergh-bubble>
