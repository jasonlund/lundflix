<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command('plex:sync-servers')->everyFifteenMinutes();

// Sync TV shows and movies daily at 2:00 AM Pacific, then ratings (which depend on both existing)
Schedule::command('tvmaze:sync-shows')
    ->daily()
    ->at('02:00')
    ->timezone('America/Los_Angeles')
    ->then(fn () => Artisan::call('tvmaze:sync-updates'))
    ->then(fn () => Artisan::call('imdb:sync-movies'))
    ->then(fn () => Artisan::call('imdb:sync-ratings'))
    ->then(fn () => Artisan::call('tmdb:sync-movies'))
    ->then(fn () => Artisan::call('tvmaze:sync-schedule'))
    ->then(fn () => Artisan::call('fanart:sync-updates'));
