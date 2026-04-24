@props([
    'model',
    'title',
    'logoUrl' => null,
])

<div class="relative overflow-hidden">
    <div class="relative flex flex-col items-center gap-2 py-5 text-white sm:py-6">
        <div class="min-h-28 max-w-4xl self-start sm:min-h-40">
            <x-artwork
                :model="$model"
                type="logo"
                :alt="$title . ' logo'"
                :fallback="false"
                class="h-20 max-w-[80vw] drop-shadow sm:h-28"
            />
        </div>

        <div>
            <flux:heading
                size="xl"
                level="1"
                class="{{ $logoUrl ? 'truncate' : 'line-clamp-2 text-3xl text-balance' }} font-serif tracking-wide"
            >
                {{ $title }}
            </flux:heading>
            {{ $subtitle ?? '' }}
        </div>

        <div class="flex items-center gap-4">
            {{ $actions }}
        </div>

        <div class="flex flex-wrap items-center text-sm text-zinc-200 sm:text-xs">
            {{ $metadata }}
        </div>

        @if (isset($genres) && $genres->isNotEmpty())
            <div class="flex flex-wrap gap-4 text-sm text-zinc-200 sm:text-xs">
                {{ $genres }}
            </div>
        @endif
    </div>
</div>
