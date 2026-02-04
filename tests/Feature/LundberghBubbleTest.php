<?php

use Illuminate\Support\Facades\Blade;

it('renders with the default margin', function () {
    $html = Blade::render('<x-lundbergh-bubble>Howdy</x-lundbergh-bubble>');

    expect($html)
        ->toContain('mt-3')
        ->not->toContain('mt-0');
});

it('can render without a margin', function () {
    $html = Blade::render('<x-lundbergh-bubble :with-margin="false">Howdy</x-lundbergh-bubble>');

    expect($html)
        ->toContain('mt-0')
        ->not->toContain('mt-3');
});

it('renders with info variant by default', function () {
    $html = Blade::render('<x-lundbergh-bubble>Test message</x-lundbergh-bubble>');

    expect($html)
        ->toContain('Test message')
        ->toContain('border-zinc-200')
        ->not->toContain('border-red-200');
});

it('renders with error variant', function () {
    $html = Blade::render('<x-lundbergh-bubble variant="error">Error message</x-lundbergh-bubble>');

    expect($html)
        ->toContain('Error message')
        ->toContain('border-red-200')
        ->toContain('dark:border-red-500/30');
});

it('renders with paragraph tag by default', function () {
    $html = Blade::render('<x-lundbergh-bubble>Test</x-lundbergh-bubble>');

    expect($html)
        ->toContain('<p class="leading-6">Test</p>');
});

it('renders with div tag when specified', function () {
    $html = Blade::render('<x-lundbergh-bubble content-tag="div">Test</x-lundbergh-bubble>');

    expect($html)
        ->toContain('<div class="leading-6">Test</div>')
        ->not->toContain('<p class="leading-6">');
});

it('uses default image source', function () {
    $html = Blade::render('<x-lundbergh-bubble>Test</x-lundbergh-bubble>');

    expect($html)
        ->toContain('lundbergh-head')
        ->toContain('.png')
        ->toContain('alt="Lundbergh"');
});

it('accepts custom image source and alt text', function () {
    $html = Blade::render('<x-lundbergh-bubble image-src="/custom.png" image-alt="Custom">Test</x-lundbergh-bubble>');

    expect($html)
        ->toContain('src="/custom.png"')
        ->toContain('alt="Custom"');
});

it('accepts custom bubble classes', function () {
    $html = Blade::render('<x-lundbergh-bubble bubble-class="custom-class">Test</x-lundbergh-bubble>');

    expect($html)
        ->toContain('custom-class');
});

it('renders slot content', function () {
    $html = Blade::render('<x-lundbergh-bubble>This is slot content</x-lundbergh-bubble>');

    expect($html)
        ->toContain('This is slot content');
});

it('renders message prop instead of slot when provided', function () {
    $html = Blade::render('<x-lundbergh-bubble message="Prop message">Slot content</x-lundbergh-bubble>');

    expect($html)
        ->toContain('Prop message')
        ->not->toContain('Slot content');
});
