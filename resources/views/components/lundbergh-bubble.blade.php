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

$bubbleClass = is_array($bubbleClass) ? implode(' ', $bubbleClass) : $bubbleClass;

$avatarClasses = match ($variant) {
    'error' => 'size-8 shrink-0 overflow-hidden rounded-full border border-red-200 bg-red-50 shadow-sm ring-1 ring-red-100/70 dark:border-red-500/30 dark:bg-red-950/20 dark:ring-red-500/15',
    default => 'size-8 shrink-0 overflow-hidden rounded-full border border-zinc-200 bg-zinc-50 shadow-sm dark:border-zinc-700 dark:bg-zinc-900/60',
};

$bubbleBaseClasses = 'relative rounded-2xl border px-3 py-2 text-sm leading-6 shadow-sm before:absolute before:left-[-7px] before:top-[11px] before:border-y-[7px] before:border-r-[7px] before:border-y-transparent before:content-[\'\'] after:absolute after:left-[-6px] after:top-[12px] after:border-y-[6px] after:border-r-[6px] after:border-y-transparent after:content-[\'\']';

$bubbleClasses = match ($variant) {
    'error' => $bubbleBaseClasses.' border-red-200 bg-zinc-50 text-zinc-600 before:border-r-red-200 after:border-r-zinc-50 dark:border-red-500/30 dark:bg-zinc-900 dark:text-zinc-300 dark:before:border-r-red-500/30 dark:after:border-r-zinc-900',
    default => $bubbleBaseClasses.' border-zinc-200 bg-zinc-50 text-zinc-600 before:border-r-zinc-200 after:border-r-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:before:border-r-zinc-700 dark:after:border-r-zinc-900',
};

$bubbleClasses = trim($bubbleClasses.' '.$bubbleClass);

$wrapperClasses = ($withMargin ? 'mt-3' : 'mt-0').' flex items-start gap-3';
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
        @if ($contentTag === 'div')
            <div class="leading-6">{{ $message ?? $slot }}</div>
        @else
            <p class="leading-6">{{ $message ?? $slot }}</p>
        @endif
    </div>
</div>
