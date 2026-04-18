<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command('plex:sync-servers')->everyFifteenMinutes();

Schedule::command('process:show-subscriptions')->everyFifteenMinutes();
Schedule::command('process:movie-subscriptions')->dailyAt('08:00')->timezone('America/New_York');

Schedule::command('sync:nightly')
    ->daily()
    ->at('02:00')
    ->timezone('America/Los_Angeles')
    ->withoutOverlapping();
