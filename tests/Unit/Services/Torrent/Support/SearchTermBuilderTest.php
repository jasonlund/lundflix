<?php

use App\Services\Torrent\Support\SearchTermBuilder;

it('strips punctuation and collapses whitespace', function (string $name, string $expected) {
    expect(SearchTermBuilder::sanitize($name))->toBe($expected);
})->with([
    ['The Office', 'The Office'],
    ['Marvel\'s Agents of S.H.I.E.L.D.', 'Marvels Agents of SHIELD'],
    ['Spider-Man', 'Spider Man'],
    ['Café  Society', 'Café Society'],
    ['  padded   name  ', 'padded name'],
    ['!!!', ''],
    ['', ''],
]);

it('returns stored terms when present, trimming and filtering blanks', function () {
    expect(SearchTermBuilder::resolveTerms(['  Alias  ', '', 'Other', 5], 'Fallback'))
        ->toBe(['Alias', 'Other']);
});

it('falls back to a sanitized name when stored terms are empty', function (mixed $stored) {
    expect(SearchTermBuilder::resolveTerms($stored, 'Spider-Man'))
        ->toBe(['Spider Man']);
})->with([
    'null' => [null],
    'empty array' => [[]],
    'blanks only' => [['', '   ']],
    'non-string' => ['not-an-array'],
]);

it('returns an empty list when no terms and fallback sanitizes to empty', function () {
    expect(SearchTermBuilder::resolveTerms(null, '!!!'))->toBe([]);
    expect(SearchTermBuilder::resolveTerms([], null))->toBe([]);
});

it('returns a single term unquoted', function () {
    expect(SearchTermBuilder::buildOrQuery(['The Office']))->toBe('The Office');
});

it('joins multiple terms with quoted OR syntax', function () {
    expect(SearchTermBuilder::buildOrQuery(['The Office', 'Office US']))
        ->toBe('"The Office"|"Office US"');
});

it('strips embedded quotes when building an OR query', function () {
    expect(SearchTermBuilder::buildOrQuery(['Say "Hi"', 'Other']))
        ->toBe('"Say Hi"|"Other"');
});
