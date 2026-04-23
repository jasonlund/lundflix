@props([
    'model',
    'title',
    'logoUrl' => null,
])

<div class="relative overflow-hidden">
    <div class="relative flex flex-col items-center gap-2 py-5 text-white sm:py-6">
        <div class="max-w-4xl self-start">
            <x-artwork
                :model="$model"
                type="logo"
                :alt="$title . ' logo'"
                :fallback="false"
                class="h-28 drop-shadow sm:h-40"
            />
        </div>

        <div class="{{ $logoUrl ? '' : 'flex h-36 items-end sm:h-48' }}">
            <flux:heading
                size="xl"
                class="{{ $logoUrl ? 'truncate' : 'line-clamp-2 text-3xl' }} font-serif tracking-wide"
            >
                {{ $title }}
            </flux:heading>
            {{ $subtitle ?? '' }}
        </div>

        <div class="flex items-center gap-4">
            {{ $actions }}
        </div>

        <div class="truncate text-sm text-zinc-200 sm:text-xs">
            {{ $metadata }}
        </div>

        @if (isset($genres) && $genres->isNotEmpty())
            <div class="flex gap-4 truncate text-sm text-zinc-200 sm:text-xs">
                {{ $genres }}
            </div>
        @endif
    </div>
</div>
