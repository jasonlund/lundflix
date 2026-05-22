@props([
    'char' => null,
    'country' => null,
])

@php
    if ($country) {
        $codepoints = \App\Support\Emoji::flagCodepoints($country);
        $alt = strtoupper($country);
        $fallback = collect(str_split(strtoupper($country)))
            ->map(fn (string $c): string => mb_chr(ord($c) - ord('A') + 0x1f1e6))
            ->join('');
    } else {
        $codepoints = \App\Support\Emoji::codepoints((string) $char);
        $alt = (string) $char;
        $fallback = (string) $char;
    }

    $src = \App\Support\Emoji::assetUrl($codepoints);
@endphp

@if ($src)
    <img
        src="{{ $src }}"
        alt="{{ $alt }}"
        loading="lazy"
        decoding="async"
        {{ $attributes->class('inline-block size-[1em] shrink-0 align-[-0.15em]') }}
    />
@else
    <span {{ $attributes->class('inline-block') }} aria-label="{{ $alt }}">{{ $fallback }}</span>
@endif
