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
