<?php

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

it('runs all commands and returns success when none fail', function () {
    $ran = collect();

    $commands = [
        'tvmaze:sync-shows',
        'tvmaze:sync-updates',
        'imdb:sync-movies',
        'imdb:sync-ratings',
        'tmdb:sync-movies',
        'tmdb:sync-shows',
        'tvmaze:sync-schedule',
    ];

    foreach ($commands as $name) {
        Artisan::command($name, function () use ($name, $ran) {
            $ran->push($name);

            return Command::SUCCESS;
        });
    }

    $this->artisan('sync:nightly')
        ->assertSuccessful();

    expect($ran->all())->toBe($commands);
});

it('continues running after a command throws and returns failure status', function () {
    $ran = collect();

    $commands = [
        'tvmaze:sync-shows',
        'tvmaze:sync-updates',
        'imdb:sync-movies',
        'imdb:sync-ratings',
        'tmdb:sync-movies',
        'tmdb:sync-shows',
        'tvmaze:sync-schedule',
    ];

    foreach ($commands as $name) {
        Artisan::command($name, function () use ($name, $ran) {
            $ran->push($name);

            if ($name === 'imdb:sync-movies') {
                throw new RuntimeException('Connection refused');
            }

            return Command::SUCCESS;
        });
    }

    $this->artisan('sync:nightly')
        ->assertFailed();

    expect($ran->all())->toBe($commands);
});

it('returns failure status when a subcommand returns a non-zero exit code', function () {
    Artisan::command('tvmaze:sync-shows', fn () => Command::FAILURE);
    Artisan::command('tvmaze:sync-updates', fn () => Command::SUCCESS);
    Artisan::command('imdb:sync-movies', fn () => Command::SUCCESS);
    Artisan::command('imdb:sync-ratings', fn () => Command::SUCCESS);
    Artisan::command('tmdb:sync-movies', fn () => Command::SUCCESS);
    Artisan::command('tmdb:sync-shows', fn () => Command::SUCCESS);
    Artisan::command('tvmaze:sync-schedule', fn () => Command::SUCCESS);

    $this->artisan('sync:nightly')
        ->assertFailed();
});
