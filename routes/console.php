<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command('plex:sync-servers')->everyFifteenMinutes();
Schedule::command('plex:poll-library')->everyMinute()->withoutOverlapping();

Schedule::command('process:show-subscriptions')->everyFiveMinutes()
    ->then(fn () => Artisan::call('process:show-availability'));

Schedule::command('process:movie-subscriptions')->everyFifteenMinutes()
    ->then(fn () => Artisan::call('process:movie-availability'));

Schedule::command('sync:nightly')
    ->daily()
    ->at('02:00')
    ->timezone('America/Los_Angeles')
    ->withoutOverlapping();
