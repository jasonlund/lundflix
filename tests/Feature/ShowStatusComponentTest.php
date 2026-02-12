<?php

use App\Enums\ShowStatus;
use Illuminate\Support\Facades\Blade;

it('renders the correct icon and color for each status', function (ShowStatus $status, string $icon, string $colorClass) {
    $html = Blade::render(
        '<x-show-status :status="$status" />',
        ['status' => $status]
    );

    expect($html)
        ->toContain($status->value)
        ->toContain($colorClass)
        ->toContain('data-flux-icon');
})->with([
    'running' => [ShowStatus::Running, 'circle-play', 'text-green-400'],
    'ended' => [ShowStatus::Ended, 'circle-check', 'text-red-400'],
    'to be determined' => [ShowStatus::ToBeDetermined, 'circle-alert', 'text-yellow-400'],
    'in development' => [ShowStatus::InDevelopment, 'hammer', 'text-blue-400'],
]);
