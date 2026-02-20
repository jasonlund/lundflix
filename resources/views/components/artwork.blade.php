@props([
    'model' => null,
    'type' => 'logo',
    'alt' => '',
    'preview' => false,
])

@php
    $url = $model?->artUrl($type, $preview);
    $aspectClass = match ($type) {
        'poster' => 'aspect-[1000/1426]',
        'background' => 'aspect-[1920/1080]',
        default => 'aspect-[1000/562]',
    };
    $name = $model->name ?? ($model->title ?? '');
    $fallbackTextClass = $preview ? 'line-clamp-2 text-sm leading-tight' : 'truncate text-5xl';
@endphp

@if ($url)
    <div
        {{ $attributes }}
        x-data="{ failed: false }"
        x-init="
            $nextTick(() => {
                if ($refs.img.complete && $refs.img.naturalWidth === 0) failed = true
            })
        "
    >
        <img
            x-ref="img"
            src="{{ $url }}"
            alt="{{ $alt }}"
            loading="lazy"
            x-show="!failed"
            x-on:error="failed = true"
            class="{{ $aspectClass }} h-full w-auto object-contain"
        />
        <div x-show="failed" x-cloak class="flex h-full items-center">
            @if ($slot->isEmpty())
                @if ($type === 'background')
                    <div class="{{ $aspectClass }} bg-black"></div>
                @else
                    <div class="{{ $aspectClass }} flex w-full items-center justify-center">
                        <span class="{{ $fallbackTextClass }} w-full text-center text-zinc-400">
                            {{ $name }}
                        </span>
                    </div>
                @endif
            @else
                {{ $slot }}
            @endif
        </div>
    </div>
@else
    <div {{ $attributes }}>
        @if ($slot->isEmpty())
            @if ($type === 'background')
                <div class="{{ $aspectClass }} bg-black"></div>
            @else
                <div class="{{ $aspectClass }} flex w-full items-center justify-center">
                    <span class="{{ $fallbackTextClass }} w-full text-center text-zinc-400">
                        {{ $name }}
                    </span>
                </div>
            @endif
        @else
            {{ $slot }}
        @endif
    </div>
@endif
