<?php

use App\Enums\IptCategory;

it('builds query string from categories', function () {
    $result = IptCategory::queryString([IptCategory::TvPacks, IptCategory::TvX265]);

    expect($result)->toBe('65=&99=');
});

it('builds query string for a single category', function () {
    $result = IptCategory::queryString([IptCategory::MovieX265]);

    expect($result)->toBe('100=');
});

it('returns only movie cases from movieCases', function () {
    $cases = IptCategory::movieCases();

    expect($cases)->toHaveCount(15);
    foreach ($cases as $case) {
        expect($case->name)->toStartWith('Movie');
    }
});

it('returns only TV cases from tvCases', function () {
    $cases = IptCategory::tvCases();

    expect($cases)->toHaveCount(15);
    foreach ($cases as $case) {
        expect($case->name)->not->toStartWith('Movie');
    }
});

it('generates options array from cases', function () {
    $options = IptCategory::options(IptCategory::movieCases());

    expect($options)
        ->toBeArray()
        ->toHaveCount(15);

    foreach ($options as $key => $label) {
        expect($key)->toBeInt();
        expect($label)->toBeString()->not->toBeEmpty();
    }
});

it('returns default movie values', function () {
    expect(IptCategory::defaultMovieValues())->toBe([IptCategory::MovieX265->value]);
});

it('returns default TV values', function () {
    expect(IptCategory::defaultTvValues())->toBe([
        IptCategory::TvPacks->value,
        IptCategory::TvX265->value,
    ]);
});
