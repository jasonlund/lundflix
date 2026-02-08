<?php

use App\Models\Movie;
use App\Models\Show;
use Livewire\Livewire;

it('navigates to show page when show result is selected', function () {
    $show = Show::factory()->create();

    Livewire::test('media-search')
        ->call('selectResult', 'show', $show->id)
        ->assertRedirect(route('shows.show', $show));
});

it('navigates to movie page when movie result is selected', function () {
    $movie = Movie::factory()->create();

    Livewire::test('media-search')
        ->call('selectResult', 'movie', $movie->id)
        ->assertRedirect(route('movies.show', $movie));
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

it('renders preview poster art for results', function (string $type, callable $factory): void {
    $model = $factory();

    $expectedUrl = route('art', [
        'mediable' => $type,
        'id' => $model->id,
        'type' => 'poster',
        'preview' => 1,
    ]);

    Livewire::test('media-search')
        ->set('query', $model->imdb_id)
        ->assertSeeHtml('src="'.$expectedUrl.'"');
})->with([
    'show' => ['show', fn (): Show => Show::factory()->create(['imdb_id' => 'tt8000001'])],
    'movie' => ['movie', fn (): Movie => Movie::factory()->create(['imdb_id' => 'tt8000002'])],
]);
