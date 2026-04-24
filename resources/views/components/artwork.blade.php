@props([
    'model' => null,
    'type' => 'logo',
    'alt' => '',
    'fallback' => true,
    'size' => null,
])

@php
    $url = $model?->artUrl($type, $size);
    $aspectClass = match ($type) {
        'poster' => 'aspect-[1000/1426]',
        'background' => 'aspect-[1920/1080]',
        default => 'aspect-[1000/562]',
    };
    $name = $model->name ?? ($model->title ?? '');
    $fallbackTextClass = $size ? 'line-clamp-2 text-sm leading-5' : 'truncate text-5xl';
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
            @if ($slot->isNotEmpty())
                {{ $slot }}
            @elseif ($fallback)
                @if ($type === 'background')
                    <div class="{{ $aspectClass }} bg-black"></div>
                @else
                    <div class="{{ $aspectClass }} flex w-full items-center justify-center">
                        <span class="{{ $fallbackTextClass }} w-full text-center text-zinc-400">
                            {{ $name }}
                        </span>
                    </div>
                @endif
            @endif
        </div>
    </div>
@elseif ($slot->isNotEmpty())
    <div {{ $attributes }}>
        {{ $slot }}
    </div>
@elseif ($fallback)
    <div {{ $attributes }}>
        @if ($type === 'background')
            <div class="{{ $aspectClass }} bg-black"></div>
        @else
            <div class="{{ $aspectClass }} flex w-full items-center justify-center">
                <span class="{{ $fallbackTextClass }} w-full text-center text-zinc-400">
                    {{ $name }}
                </span>
            </div>
        @endif
    </div>
@endif
