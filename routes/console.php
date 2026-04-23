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

Schedule::command('process:show-availability')->everyFiveMinutes()
    ->then(fn () => Artisan::call('process:show-subscriptions'));

Schedule::command('process:movie-availability')->everyFifteenMinutes()
    ->then(fn () => Artisan::call('process:movie-subscriptions'));

Schedule::command('sync:nightly')
    ->daily()
    ->at('02:00')
    ->timezone('America/Los_Angeles')
    ->withoutOverlapping();
