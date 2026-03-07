<?php

use App\Enums\ArtworkType;
use App\Models\Media;
use App\Models\Movie;
use App\Models\Show;
use Illuminate\Support\Facades\Blade;

it('renders img with correct src for a show', function () {
    $show = Show::factory()->create();
    Media::factory()->active()->create([
        'mediable_type' => Show::class,
        'mediable_id' => $show->id,
        'type' => ArtworkType::Logo->value,
    ]);

    $html = Blade::render(
        '<x-artwork :model="$model" type="logo" alt="Test logo" />',
        ['model' => $show]
    );

    $expectedUrl = route('art', ['mediable' => 'show', 'id' => $show->sqid, 'type' => 'logo']);

    expect($html)
        ->toContain('src="'.$expectedUrl.'"')
        ->toContain('alt="Test logo"')
        ->toContain('aspect-[1000/562]')
        ->toContain('x-ref="img"')
        ->toContain('x-init');
});

it('renders img with correct src for a movie', function () {
    $movie = Movie::factory()->create();
    Media::factory()->active()->create([
        'mediable_type' => Movie::class,
        'mediable_id' => $movie->id,
        'type' => ArtworkType::Logo->value,
    ]);

    $html = Blade::render(
        '<x-artwork :model="$model" type="logo" alt="Movie logo" />',
        ['model' => $movie]
    );

    $expectedUrl = route('art', ['mediable' => 'movie', 'id' => $movie->sqid, 'type' => 'logo']);

    expect($html)->toContain('src="'.$expectedUrl.'"');
});

it('renders default fallback with show name for logo type', function () {
    $show = Show::factory()->create(['name' => 'Breaking Bad', 'tmdb_id' => null]);

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
    $movie = Movie::factory()->create(['title' => 'The Matrix', 'tmdb_id' => null]);

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
    $show = Show::factory()->create(['name' => 'The Wire', 'tmdb_id' => null]);

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
    $show = Show::factory()->create(['name' => 'The Wire', 'tmdb_id' => null]);

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

it('renders fallback when model has tmdb_id but no active media', function () {
    $show = Show::factory()->create(['name' => 'Breaking Bad']);

    $html = Blade::render(
        '<x-artwork :model="$model" type="logo" alt="Test" />',
        ['model' => $show]
    );

    expect($html)
        ->toContain('Breaking Bad')
        ->not->toContain('<img');
});

it('renders nothing when fallback is false and no artwork exists', function () {
    $show = Show::factory()->create(['name' => 'Nirvanna the Band', 'tmdb_id' => null]);

    $html = Blade::render(
        '<x-artwork :model="$model" type="logo" alt="Test" :fallback="false" />',
        ['model' => $show]
    );

    expect(trim($html))->toBeEmpty();
});

it('still renders fallback text by default when no artwork exists', function () {
    $show = Show::factory()->create(['name' => 'Nirvanna the Band', 'tmdb_id' => null]);

    $html = Blade::render(
        '<x-artwork :model="$model" type="logo" alt="Test" />',
        ['model' => $show]
    );

    expect($html)->toContain('Nirvanna the Band');
});

it('appends size query param to art url when size prop is provided', function () {
    $show = Show::factory()->create();
    Media::factory()->active()->create([
        'mediable_type' => Show::class,
        'mediable_id' => $show->id,
        'type' => ArtworkType::Logo->value,
    ]);

    $html = Blade::render(
        '<x-artwork :model="$model" type="logo" alt="Test" size="w200" />',
        ['model' => $show]
    );

    $expectedUrl = route('art', ['mediable' => 'show', 'id' => $show->sqid, 'type' => 'logo']).'?size=w200';

    expect($html)->toContain('src="'.$expectedUrl.'"');
});

it('uses compact fallback text when size prop is set', function () {
    $show = Show::factory()->create(['name' => 'Breaking Bad', 'tmdb_id' => null]);

    $html = Blade::render(
        '<x-artwork :model="$model" type="logo" alt="Test" size="w200" />',
        ['model' => $show]
    );

    expect($html)
        ->toContain('line-clamp-2')
        ->toContain('text-sm');
});

it('passes attributes through to the outer container', function () {
    $show = Show::factory()->create();
    Media::factory()->active()->create([
        'mediable_type' => Show::class,
        'mediable_id' => $show->id,
        'type' => ArtworkType::Logo->value,
    ]);

    $html = Blade::render(
        '<x-artwork :model="$model" type="logo" alt="Test" class="custom-class" data-testid="art" />',
        ['model' => $show]
    );

    expect($html)
        ->toContain('custom-class')
        ->toContain('data-testid="art"');
});
