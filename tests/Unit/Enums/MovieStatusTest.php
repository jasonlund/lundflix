<?php

use App\Enums\MovieStatus;

it('returns a non-empty label for every case', function (MovieStatus $status) {
    expect($status->getLabel())->toBeString()->not->toBeEmpty();
})->with(MovieStatus::cases());

it('returns a non-empty color for every case', function (MovieStatus $status) {
    expect($status->getColor())->toBeString()->not->toBeEmpty();
})->with(MovieStatus::cases());

it('returns a heroicon for every case', function (MovieStatus $status) {
    expect($status->getIcon())->toBeInstanceOf(\Filament\Support\Icons\Heroicon::class);
})->with(MovieStatus::cases());

it('returns a non-empty icon string for every case', function (MovieStatus $status) {
    expect($status->icon())->toBeString()->not->toBeEmpty();
})->with(MovieStatus::cases());

it('returns a tailwind color class for every case', function (MovieStatus $status) {
    expect($status->iconColorClass())->toBeString()->toStartWith('text-');
})->with(MovieStatus::cases());

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
