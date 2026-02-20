<?php

use App\Enums\MovieStatus;
use App\Enums\ShowStatus;
use Illuminate\Support\Facades\Blade;

it('renders the correct icon for each show status', function (ShowStatus $status, string $icon) {
    $html = Blade::render(
        '<x-media-status :status="$status" />',
        ['status' => $status]
    );

    expect($html)
        ->toContain($status->value)
        ->toContain('data-flux-icon');
})->with([
    'running' => [ShowStatus::Running, 'tv-minimal-play'],
    'ended' => [ShowStatus::Ended, 'circle-check'],
    'to be determined' => [ShowStatus::ToBeDetermined, 'circle-alert'],
    'in development' => [ShowStatus::InDevelopment, 'hammer'],
]);

it('renders the correct icon for each movie status', function (MovieStatus $status, string $icon) {
    $html = Blade::render(
        '<x-media-status :status="$status" />',
        ['status' => $status]
    );

    expect($html)
        ->toContain($status->value)
        ->toContain('data-flux-icon');
})->with([
    'rumored' => [MovieStatus::Rumored, 'message-circle'],
    'planned' => [MovieStatus::Planned, 'hammer'],
    'in production' => [MovieStatus::InProduction, 'film'],
    'post production' => [MovieStatus::PostProduction, 'sliders-horizontal'],
    'festival release' => [MovieStatus::FestivalRelease, 'drama'],
    'limited release' => [MovieStatus::LimitedRelease, 'map-pin'],
    'upcoming' => [MovieStatus::Upcoming, 'calendar'],
    'in theaters' => [MovieStatus::InTheaters, 'ticket'],
    'released' => [MovieStatus::Released, 'videotape'],
    'canceled' => [MovieStatus::Canceled, 'circle-x'],
]);
