<?php

use App\Models\Movie;
use App\Models\Show;
use Livewire\Livewire;

it('renders show link in search results', function () {
    $show = Show::factory()->create(['imdb_id' => 'tt9000001']);

    Livewire::test('media-search')
        ->set('query', $show->imdb_id)
        ->assertSeeHtml('href="'.route('shows.show', $show).'"');
});

it('renders movie link in search results', function () {
    $movie = Movie::factory()->create(['imdb_id' => 'tt9000002']);

    Livewire::test('media-search')
        ->set('query', $movie->imdb_id)
        ->assertSeeHtml('href="'.route('movies.show', $movie).'"');
});

it('renders stable keys for search results', function (string $type, callable $factory): void {
    $model = $factory();

    Livewire::test('media-search')
        ->set('query', $model->imdb_id)
        ->assertSeeHtml('wire:key="search-result-'.$type.'-'.$model->id.'"');
})->with([
    'show' => ['show', fn (): Show => Show::factory()->create(['imdb_id' => 'tt7000001'])],
    'movie' => ['movie', fn (): Movie => Movie::factory()->create(['imdb_id' => 'tt7000002'])],
]);

it('shows imdb tip callout when searching', function () {
    Livewire::test('media-search')
        ->set('query', 'something')
        ->assertSeeHtml('go ahead and try an')
        ->assertSeeHtml('href="https://www.imdb.com"')
        ->assertSeeHtml('IMDb');
});

it('does not show imdb tip callout when searching by imdb id', function () {
    Livewire::test('media-search')
        ->set('query', 'tt1439629')
        ->assertDontSeeHtml('go ahead and try an');
});

it('does not show imdb tip callout for short queries', function () {
    Livewire::test('media-search')
        ->set('query', 'a')
        ->assertDontSeeHtml('go ahead and try an');
});

it('renders preview poster art for results', function (string $type, string $artType, callable $factory): void {
    $model = $factory();

    $expectedUrl = route('art', [
        'mediable' => $type,
        'id' => $model->id,
        'type' => $artType,
        'preview' => 1,
    ]);

    Livewire::test('media-search')
        ->set('query', $model->imdb_id)
        ->assertSeeHtml('src="'.$expectedUrl.'"');
})->with([
    'show' => ['show', 'logo', fn (): Show => Show::factory()->create(['imdb_id' => 'tt8000001'])],
    'movie' => ['movie', 'logo', fn (): Movie => Movie::factory()->create(['imdb_id' => 'tt8000002'])],
]);
