@props([
    'imageAlt' => 'Lundbergh',
    'imageSrc' => Vite::image('lundbergh-head.png'),
    'message' => null,
])

<div {{ $attributes->class('mt-3 flex items-start gap-3') }}>
    <div
        class="size-8 shrink-0 overflow-hidden rounded-full border border-zinc-200 bg-zinc-50 shadow-sm dark:border-zinc-700 dark:bg-zinc-900/60"
    >
        <img
            src="{{ $imageSrc }}"
            alt="{{ $imageAlt }}"
            class="h-full w-full origin-top scale-[2.05] object-cover object-[50%_2%]"
        />
    </div>
    <div
        class="relative rounded-2xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm leading-6 text-zinc-600 shadow-sm before:absolute before:left-[-7px] before:top-[11px] before:border-y-[7px] before:border-r-[7px] before:border-y-transparent before:border-r-zinc-200 before:content-[''] after:absolute after:left-[-6px] after:top-[12px] after:border-y-[6px] after:border-r-[6px] after:border-y-transparent after:border-r-zinc-50 after:content-[''] dark:border-zinc-700 dark:bg-zinc-900/60 dark:text-zinc-300 dark:before:border-r-zinc-700 dark:after:border-r-zinc-900/60"
    >
        <p class="leading-6">{{ $message ?? $slot }}</p>
    </div>
</div>
