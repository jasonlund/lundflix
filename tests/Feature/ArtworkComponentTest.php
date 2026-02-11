<?php

use App\Models\Movie;
use App\Models\Show;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;

it('renders img with correct src for a show', function () {
    $show = Show::factory()->create();

    $html = Blade::render(
        '<x-artwork :model="$model" type="logo" alt="Test logo" />',
        ['model' => $show]
    );

    $expectedUrl = route('art', ['mediable' => 'show', 'id' => $show->id, 'type' => 'logo']);

    expect($html)
        ->toContain('src="'.$expectedUrl.'"')
        ->toContain('alt="Test logo"')
        ->toContain('aspect-[1000/562]')
        ->toContain('x-ref="img"')
        ->toContain('x-init');
});

it('renders img with correct src for a movie', function () {
    $movie = Movie::factory()->create();

    $html = Blade::render(
        '<x-artwork :model="$model" type="logo" alt="Movie logo" />',
        ['model' => $movie]
    );

    $expectedUrl = route('art', ['mediable' => 'movie', 'id' => $movie->id, 'type' => 'logo']);

    expect($html)->toContain('src="'.$expectedUrl.'"');
});

it('renders default fallback with show name for logo type', function () {
    $show = Show::factory()->create(['name' => 'Breaking Bad', 'thetvdb_id' => null]);

    $html = Blade::render(
        '<x-artwork :model="$model" type="logo" alt="Test" />',
        ['model' => $show]
    );

    expect($html)
        ->toContain('Breaking Bad')
        ->toContain('aspect-[1000/562]')
        ->not->toContain('<img');
});

it('renders default fallback with movie title for poster type', function () {
    $movie = Movie::factory()->create(['title' => 'The Matrix']);

    Cache::put($movie->artMissingCacheKey(), true);

    $html = Blade::render(
        '<x-artwork :model="$model" type="poster" alt="Test" />',
        ['model' => $movie]
    );

    expect($html)
        ->toContain('The Matrix')
        ->toContain('aspect-[1000/1426]')
        ->not->toContain('<img');
});

it('renders black background div for background type without name', function () {
    $show = Show::factory()->create(['name' => 'The Wire', 'thetvdb_id' => null]);

    $html = Blade::render(
        '<x-artwork :model="$model" type="background" alt="Test" />',
        ['model' => $show]
    );

    expect($html)
        ->toContain('aspect-[1920/1080]')
        ->toContain('bg-black')
        ->not->toContain('The Wire')
        ->not->toContain('<img');
});

it('renders slot content when provided instead of default fallback', function () {
    $show = Show::factory()->create(['name' => 'The Wire', 'thetvdb_id' => null]);

    $html = Blade::render(
        '<x-artwork :model="$model" type="logo" alt="Test">Custom Fallback</x-artwork>',
        ['model' => $show]
    );

    expect($html)
        ->toContain('Custom Fallback')
        ->not->toContain('The Wire');
});

it('renders slot content when model is null', function () {
    $html = Blade::render(
        '<x-artwork :model="null" type="logo" alt="Test">No Model</x-artwork>',
    );

    expect($html)
        ->toContain('No Model')
        ->not->toContain('<img');
});

it('adds preview query param when preview is true', function () {
    $show = Show::factory()->create();

    $html = Blade::render(
        '<x-artwork :model="$model" type="logo" alt="Test" :preview="true" />',
        ['model' => $show]
    );

    expect($html)->toContain('preview=1');
});

it('does not add preview query param by default', function () {
    $show = Show::factory()->create();

    $html = Blade::render(
        '<x-artwork :model="$model" type="logo" alt="Test" />',
        ['model' => $show]
    );

    expect($html)->not->toContain('preview=');
});

it('passes attributes through to the outer container', function () {
    $show = Show::factory()->create();

    $html = Blade::render(
        '<x-artwork :model="$model" type="logo" alt="Test" class="custom-class" data-testid="art" />',
        ['model' => $show]
    );

    expect($html)
        ->toContain('custom-class')
        ->toContain('data-testid="art"');
});
