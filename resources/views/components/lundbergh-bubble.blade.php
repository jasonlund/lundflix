@props([
    'imageAlt' => 'Lundbergh',
    'imageSrc' => Vite::image('lundbergh-head.png'),
    'message' => null,
    'variant' => 'info',
    'contentTag' => 'p',
    'withMargin' => true,
    'bubbleClass' => '',
    'size' => 'small',
    'showAvatar' => true,
    'showTail' => true,
])

@php
    $variant = $variant === 'error' ? 'error' : 'info';

    // Single source of truth for size. The tail sits on the head's vertical center
    // (top-{head ÷ 2}) since the head is top-aligned. Add future per-size differences here.
    [$headClass, $tailTop, $tailBefore] = match ($size) {
        'large' => ['size-10', 'top-5', 'before:top-5'],
        default => ['size-8', 'top-4', 'before:top-4'],
    };

    $avatarClasses = match ($variant) {
        'error' => $headClass . ' shrink-0 overflow-hidden rounded-full border border-red-500/30 bg-red-950/20 shadow-sm ring-1 ring-red-500/15',
        default => $headClass . ' shrink-0 overflow-hidden rounded-full border border-zinc-700 bg-zinc-900/50 shadow-sm',
    };

    $bubbleBaseClasses = 'relative rounded-2xl border px-3 py-2 text-sm leading-6 shadow-sm';

    $tailPseudoBase = "before:absolute before:left-[-7px] before:-translate-y-1/2 before:border-y-[7px] before:border-r-[7px] before:border-y-transparent before:content-['']";

    $bubbleColorClasses = match ($variant) {
        'error' => 'glass-panel border-red-500/30 text-zinc-300',
        default => 'glass-panel border-zinc-700 text-zinc-300',
    };

    $tailBorderColor = match ($variant) {
        'error' => 'before:border-r-red-500/30',
        default => 'before:border-r-zinc-700',
    };

    $bubbleClasses = $bubbleBaseClasses . ' ' . $bubbleColorClasses;

    if ($showTail) {
        $bubbleClasses .= ' ' . $tailPseudoBase . ' ' . $tailBefore . ' ' . $tailBorderColor;
    }

    $arrowFillClasses = 'arrow-clip-left glass-panel absolute left-[-6px] h-3 w-[6px] -translate-y-1/2 ' . $tailTop;

    $bubbleClasses = trim($bubbleClasses . ' ' . $bubbleClass);

    $wrapperClasses = ($withMargin ? 'mt-3' : 'mt-0') . ' flex items-start gap-3';
@endphp

<div {{ $attributes->class($wrapperClasses) }}>
    @if ($showAvatar)
        <div class="{{ $avatarClasses }}">
            <img
                src="{{ $imageSrc }}"
                alt="{{ $imageAlt }}"
                class="h-full w-full origin-top scale-[2.05] object-cover object-[50%_2%]"
            />
        </div>
    @endif

    <div class="{{ $bubbleClasses }}">
        @if ($showTail)
            <div class="{{ $arrowFillClasses }}"></div>
        @endif

        @if ($contentTag === 'div')
            <div class="leading-6">{{ $message ?? $slot }}</div>
        @else
            <p class="leading-6">{{ $message ?? $slot }}</p>
        @endif
    </div>
</div>
