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
