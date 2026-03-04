<?php

use App\Enums\MovieStatus;

it('reports released as cartable', function () {
    expect(MovieStatus::Released->isCartable())->toBeTrue();
});

it('reports non-released statuses as not cartable', function (MovieStatus $status) {
    expect($status->isCartable())->toBeFalse();
})->with([
    'Rumored' => MovieStatus::Rumored,
    'Planned' => MovieStatus::Planned,
    'InProduction' => MovieStatus::InProduction,
    'PostProduction' => MovieStatus::PostProduction,
    'FestivalRelease' => MovieStatus::FestivalRelease,
    'LimitedRelease' => MovieStatus::LimitedRelease,
    'Upcoming' => MovieStatus::Upcoming,
    'InTheaters' => MovieStatus::InTheaters,
    'Canceled' => MovieStatus::Canceled,
]);
