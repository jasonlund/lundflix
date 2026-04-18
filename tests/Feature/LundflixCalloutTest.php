<?php

use Illuminate\Support\Facades\Blade;

it('applies wrapper classes to the outer Lundbergh bubble', function () {
    $html = Blade::render(
        '<x-lundflix-callout class="mt-4"><flux:callout.text>Keep your request list tidy.</flux:callout.text></x-lundflix-callout>',
    );

    $document = new DOMDocument;

    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();

    $xpath = new DOMXPath($document);

    $callout = $xpath->query('//*[@data-flux-callout]')->item(0);
    $wrapper = $xpath->query(
        'ancestor::div[contains(concat(" ", normalize-space(@class), " "), " flex ")][contains(concat(" ", normalize-space(@class), " "), " items-start ")][contains(concat(" ", normalize-space(@class), " "), " gap-3 ")]',
        $callout,
    )->item(0);

    expect($callout)->not->toBeNull()
        ->and($wrapper)->not->toBeNull()
        ->and($callout->getAttribute('class'))->not->toContain('mt-4')
        ->and($wrapper->getAttribute('class'))->toContain('mt-4');
});
