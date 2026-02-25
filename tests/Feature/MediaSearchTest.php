<?php

use App\Models\Movie;
use App\Models\Show;
use App\Support\Formatters;
use Livewire\Livewire;

it('displays show results in search', function () {
    $show = Show::factory()->create([
        'name' => 'Breaking Bad',
        'language' => 'English',
        'premiered' => '2008-01-20',
        'ended' => '2013-09-29',
        'status' => 'Ended',
        'runtime' => 47,
        'genres' => ['Drama', 'Crime'],
        'network' => ['id' => 1, 'name' => 'AMC', 'country' => ['name' => 'United States', 'code' => 'US']],
    ]);

    Livewire::test('media-search')
        ->set('query', 'Breaking')
        ->assertSee("'08-'13")
        ->assertSee('Ended')
        ->assertSee('47m')
        ->assertSee(route('shows.show', $show));
});

it('displays movie results in search', function () {
    $movie = Movie::factory()->create([
        'title' => 'The Matrix',
        'year' => 1999,
        'runtime' => 136,
        'genres' => ['Action', 'Sci-Fi'],
        'original_language' => 'en',
        'release_date' => '1999-03-31',
        'status' => 'Released',
        'production_companies' => [
            ['id' => 79, 'name' => 'Warner Bros.', 'logo_path' => null, 'origin_country' => 'US'],
        ],
    ]);
    $runtime = Formatters::runtime($movie->runtime);

    Livewire::test('media-search')
        ->set('query', 'Matrix')
        ->assertSee('03/31/99')
        ->assertSee('Released')
        ->assertSee($runtime)
        ->assertSee(route('movies.show', $movie));
});

it('clears search query when clearSearch is called', function () {
    Livewire::test('media-search')
        ->set('query', 'test query')
        ->assertSet('query', 'test query')
        ->call('clearSearch')
        ->assertSet('query', '');
});

it('renders the search modal component', function () {
    Livewire::test('media-search')
        ->assertStatus(200);
});

it('uses active styling in search results', function () {
    Show::factory()->create(['name' => 'The Wire', 'language' => 'English']);

    Livewire::test('media-search')
        ->set('query', 'Wire')
        ->assertSee('data-active:bg-zinc-700/60')
        ->assertSee('group-data-active/item:text-zinc-400');
});

it('enables auto-highlight first result in command panel', function () {
    Livewire::test('media-search')
        ->assertSeeHtml('autoHighlightFirst: true');
});

it('renders stable keys for search results', function (string $type, callable $factory): void {
    $model = $factory();

    Livewire::test('media-search')
        ->set('query', $model->imdb_id)
        ->assertSeeHtml('wire:key="search-result-'.$type.'-'.$model->id.'"');
})->with([
    'show' => ['show', fn (): Show => Show::factory()->create(['imdb_id' => 'tt7000001', 'language' => 'English'])],
    'movie' => ['movie', fn (): Movie => Movie::factory()->create(['imdb_id' => 'tt7000002', 'original_language' => 'en'])],
]);

it('shows imdb tip callout below results when there are results', function () {
    Show::factory()->create(['name' => 'Something Cool', 'language' => 'English']);

    Livewire::test('media-search')
        ->set('query', 'Something')
        ->assertSeeHtml('go ahead and try an')
        ->assertSeeHtml('href="https://www.imdb.com"')
        ->assertSee('IMDb');
});

it('shows imdb hint in empty state when no results found', function () {
    Livewire::test('media-search')
        ->set('query', 'xyznonexistent')
        ->assertSee(__('lundbergh.empty.search_no_results'))
        ->assertSee(__('lundbergh.empty.search_no_results_filter'))
        ->assertSee('Or go ahead and search by an')
        ->assertSeeHtml('href="https://www.imdb.com"')
        ->assertSee('IMDb');
});

it('shows imdb not found message when imdb id has no results', function () {
    Livewire::test('media-search')
        ->set('query', 'tt0000000')
        ->assertSee(__('lundbergh.empty.imdb_not_found'));
});

it('does not show imdb tip callout when searching by imdb id', function () {
    Livewire::test('media-search')
        ->set('query', 'tt1439629')
        ->assertDontSee('go ahead and try an')
        ->assertDontSee('Or go ahead and search by an');
});

it('does not show imdb tip callout for short queries', function () {
    Livewire::test('media-search')
        ->set('query', 'a')
        ->assertDontSee('go ahead and try an')
        ->assertDontSee('Or go ahead and search by an');
});

it('renders HD clear logo art for results', function (string $type, string $artType, callable $factory): void {
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
    'show' => ['show', 'logo', fn (): Show => Show::factory()->create(['imdb_id' => 'tt8000001', 'language' => 'English'])],
    'movie' => ['movie', 'logo', fn (): Movie => Movie::factory()->create(['imdb_id' => 'tt8000002', 'original_language' => 'en'])],
]);

it('defaults language to english', function () {
    Livewire::test('media-search')
        ->assertSet('language', 'en');
});

it('renders the language toggle', function () {
    Livewire::test('media-search')
        ->assertSeeHtml('Language filter')
        ->assertSeeHtml('data-flux-icon');
});

it('filters search results to english by default', function () {
    Show::factory()->create(['name' => 'Testshow Alpha', 'language' => 'English']);
    Show::factory()->create(['name' => 'Testshow Beta', 'language' => 'German']);

    Livewire::test('media-search')
        ->set('query', 'Testshow')
        ->assertSee('Testshow Alpha')
        ->assertDontSee('Testshow Beta');
});

it('filters search results to foreign language', function () {
    Show::factory()->create(['name' => 'Testshow Alpha', 'language' => 'English']);
    Show::factory()->create(['name' => 'Testshow Beta', 'language' => 'German']);

    Livewire::test('media-search')
        ->set('language', 'foreign')
        ->set('query', 'Testshow')
        ->assertDontSee('Testshow Alpha')
        ->assertSee('Testshow Beta');
});

it('shows all languages when language filter is empty', function () {
    Show::factory()->create(['name' => 'Testshow Alpha', 'language' => 'English']);
    Show::factory()->create(['name' => 'Testshow Beta', 'language' => 'German']);

    Livewire::test('media-search')
        ->set('language', '')
        ->set('query', 'Testshow')
        ->assertSee('Testshow Alpha')
        ->assertSee('Testshow Beta');
});

it('shows language label when filter is foreign', function () {
    Show::factory()->create(['name' => 'Testshow Gamma', 'language' => 'German']);

    Livewire::test('media-search')
        ->set('language', 'foreign')
        ->set('query', 'Testshow Gamma')
        ->assertSee('German');
});

it('shows language label when filter is all languages', function () {
    Show::factory()->create(['name' => 'Testshow Delta', 'language' => 'Japanese']);

    Livewire::test('media-search')
        ->set('language', '')
        ->set('query', 'Testshow Delta')
        ->assertSee('Japanese');
});

it('shows language label for movies when filter is foreign', function () {
    Movie::factory()->create([
        'title' => 'Testmovie Epsilon',
        'original_language' => 'fr',
    ]);

    Livewire::test('media-search')
        ->set('language', 'foreign')
        ->set('query', 'Testmovie Epsilon')
        ->assertSee('French');
});

it('hides language toggle when searching by imdb id', function () {
    Show::factory()->create(['name' => 'Some Show', 'imdb_id' => 'tt9900001', 'language' => 'English']);

    Livewire::test('media-search')
        ->set('query', 'tt9900001')
        ->assertDontSeeHtml('Language filter');
});

it('shows language label when searching by imdb id', function () {
    Show::factory()->create(['name' => 'Japanese Show', 'imdb_id' => 'tt9900002', 'language' => 'Japanese']);

    Livewire::test('media-search')
        ->set('query', 'tt9900002')
        ->assertSee('Japanese');
});

it('returns all languages when searching by imdb id regardless of language setting', function () {
    Show::factory()->create(['name' => 'English Show', 'imdb_id' => 'tt9900003', 'language' => 'English']);
    Movie::factory()->create(['title' => 'French Movie', 'imdb_id' => 'tt9900003', 'original_language' => 'fr']);

    Livewire::test('media-search')
        ->set('language', 'en')
        ->set('query', 'tt9900003')
        ->assertSee('English Show')
        ->assertSee('French Movie');
});

it('displays country code for show with network', function () {
    Show::factory()->create([
        'name' => 'Country Code Show',
        'language' => 'English',
        'network' => ['id' => 1, 'name' => 'NBC', 'country' => ['name' => 'United States', 'code' => 'US']],
    ]);

    Livewire::test('media-search')
        ->set('query', 'Country Code')
        ->assertSee('US');
});

it('displays compact year label for running shows without present', function () {
    Show::factory()->create([
        'name' => 'Ongoing Test Show',
        'language' => 'English',
        'premiered' => '2020-03-01',
        'ended' => null,
        'status' => 'Running',
    ]);

    Livewire::test('media-search')
        ->set('query', 'Ongoing Test')
        ->assertSee("'20-")
        ->assertDontSee('2020-present');
});

it('displays release date for movies in search', function () {
    Movie::factory()->create([
        'title' => 'Dated Test Movie',
        'release_date' => '2024-07-15',
        'original_language' => 'en',
    ]);

    Livewire::test('media-search')
        ->set('query', 'Dated Test')
        ->assertSee('07/15/24');
});

it('displays origin country for movies in search', function () {
    Movie::factory()->create([
        'title' => 'Country Test Movie',
        'origin_country' => ['US', 'GB'],
        'original_language' => 'en',
    ]);

    Livewire::test('media-search')
        ->set('query', 'Country Test')
        ->assertSee('US, GB');
});

it('displays movie status in search results', function () {
    Movie::factory()->create([
        'title' => 'Status Test Movie',
        'original_language' => 'en',
        'status' => 'Post Production',
    ]);

    Livewire::test('media-search')
        ->set('query', 'Status Test')
        ->assertSee('Post Production');
});

it('handles movie without status in search', function () {
    Movie::factory()->create([
        'title' => 'No Status Movie',
        'original_language' => 'en',
        'status' => null,
    ]);

    Livewire::test('media-search')
        ->set('query', 'No Status')
        ->assertSee('No Status Movie');
});

it('renders type icon for shows', function () {
    Show::factory()->create(['name' => 'Type Icon Test Show', 'language' => 'English']);

    Livewire::test('media-search')
        ->set('query', 'Type Icon Test')
        ->assertSeeHtml('data-flux-icon');
});

it('renders type icon for movies', function () {
    Movie::factory()->create(['title' => 'Type Icon Test Movie', 'original_language' => 'en']);

    Livewire::test('media-search')
        ->set('query', 'Type Icon Test')
        ->assertSeeHtml('data-flux-icon');
});

it('displays original title for foreign movies when language filter is foreign', function () {
    Movie::factory()->create([
        'title' => 'Parasite',
        'original_title' => '기생충',
        'original_language' => 'ko',
    ]);

    Livewire::test('media-search')
        ->set('language', 'foreign')
        ->set('query', 'Parasite')
        ->assertSee('기생충');
});

it('displays original title for foreign movies when language filter is all', function () {
    Movie::factory()->create([
        'title' => 'Spirited Away',
        'original_title' => '千と千尋の神隠し',
        'original_language' => 'ja',
    ]);

    Livewire::test('media-search')
        ->set('language', '')
        ->set('query', 'Spirited')
        ->assertSee('千と千尋の神隠し');
});

it('does not display original title for foreign movies when language filter is english', function () {
    Movie::factory()->create([
        'title' => 'Parasite',
        'original_title' => '기생충',
        'original_language' => 'ko',
    ]);

    Livewire::test('media-search')
        ->set('language', 'en')
        ->set('query', 'Parasite')
        ->assertDontSee('기생충');
});

it('does not display original title when it matches the display title', function () {
    Movie::factory()->create([
        'title' => 'Amélie',
        'original_title' => 'Amélie',
        'original_language' => 'fr',
    ]);

    Livewire::test('media-search')
        ->set('language', 'foreign')
        ->set('query', 'Amélie')
        ->assertSee('Amélie');
});

it('does not display original title for english movies', function () {
    Movie::factory()->create([
        'title' => 'Inception',
        'original_title' => 'Inception',
        'original_language' => 'en',
    ]);

    Livewire::test('media-search')
        ->set('language', '')
        ->set('query', 'Inception')
        ->assertDontSee('originalTitle');
});
