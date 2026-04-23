<?php

use Illuminate\Support\Facades\Blade;

test('renders the shared crt data hooks', function () {
    $html = Blade::render('<x-crt-effects />');

    expect($html)
        ->toContain('data-crt')
        ->toContain('data-crt-layer="flicker"')
        ->toContain('data-crt-layer="scanlines"')
        ->toContain('data-crt-layer="beam"')
        ->toContain('data-crt-beam');
});

test('uses tailwind utilities for the crt overlay layout', function () {
    $html = Blade::render('<x-crt-effects />');

    expect($html)
        ->toContain('pointer-events-none')
        ->toContain('absolute inset-0 overflow-hidden rounded-[inherit]')
        ->toContain('block h-px w-full');
});
