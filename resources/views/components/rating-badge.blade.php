@props([
    'certification' => null,
])

@php
    $cert = $certification !== null ? trim((string) $certification) : '';
@endphp

@if ($cert !== '')
    <span
        {{
            $attributes->class([
                'inline-flex h-5 shrink-0 items-center rounded-sm border border-white/40 bg-black/80 px-1.5 font-mono text-[0.625rem] leading-none font-bold tracking-wider text-white uppercase backdrop-blur-sm',
            ])
        }}
    >
        {{ $cert }}
    </span>
@endif
