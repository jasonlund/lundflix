@props([
    'imageAlt' => 'Lundbergh',
    'imageSrc' => Vite::image('lundbergh-head.png'),
    'message' => null,
    'variant' => 'info',
    'contentTag' => 'p',
    'withMargin' => true,
    'bubbleClass' => '',
])

@php
    $variant = $variant === 'error' ? 'error' : 'info';

    $avatarClasses = match ($variant) {
        'error' => 'size-8 shrink-0 overflow-hidden rounded-full border border-red-500/30 bg-red-950/20 shadow-sm ring-1 ring-red-500/15',
        default => 'size-8 shrink-0 overflow-hidden rounded-full border border-zinc-700 bg-zinc-900/50 shadow-sm',
    };

    $bubbleBaseClasses = 'relative rounded-2xl border px-3 py-2 text-sm leading-6 shadow-sm before:absolute before:left-[-7px] before:top-[11px] before:border-y-[7px] before:border-r-[7px] before:border-y-transparent before:content-[\'\']';

    $bubbleClasses = match ($variant) {
        'error' => $bubbleBaseClasses . ' glass-panel border-red-500/30 text-zinc-300 before:border-r-red-500/30',
        default => $bubbleBaseClasses . ' glass-panel border-zinc-700 text-zinc-300 before:border-r-zinc-700',
    };

    $arrowFillClasses = 'arrow-clip-left glass-panel absolute top-[12px] left-[-6px] h-3 w-[6px]';

    $bubbleClasses = trim($bubbleClasses . ' ' . $bubbleClass);

    $wrapperClasses = ($withMargin ? 'mt-3' : 'mt-0') . ' flex items-start gap-3';
@endphp

<div {{ $attributes->class($wrapperClasses) }}>
    <div class="{{ $avatarClasses }}">
        <img
            src="{{ $imageSrc }}"
            alt="{{ $imageAlt }}"
            class="h-full w-full origin-top scale-[2.05] object-cover object-[50%_2%]"
        />
    </div>
    <div class="{{ $bubbleClasses }}">
        <div class="{{ $arrowFillClasses }}"></div>

        @if ($contentTag === 'div')
            <div class="leading-6">{{ $message ?? $slot }}</div>
        @else
            <p class="leading-6">{{ $message ?? $slot }}</p>
        @endif
    </div>
</div>
