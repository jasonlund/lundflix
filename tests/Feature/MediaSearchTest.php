<?php

use App\Enums\NetworkLogo;
use App\Enums\StreamingLogo;
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
        'network' => ['id' => NetworkLogo::AmcUs->value, 'name' => 'AMC', 'country' => ['name' => 'United States']],
    ]);

    Livewire::test('media-search')
        ->set('query', 'Breaking')
        ->assertSee('2008-2013')
        ->assertSee('Ended')
        ->assertSee('47m')
        ->assertSee('Drama')
        ->assertSeeHtml('alt="AMC (US)"')
        ->assertSee(route('shows.show', $show));
});

it('displays movie results in search', function () {
    $movie = Movie::factory()->create([
        'title' => 'The Matrix',
        'year' => 1999,
        'runtime' => 136,
        'genres' => ['Action', 'Sci-Fi'],
        'original_language' => 'en',
    ]);
    $runtime = Formatters::runtime($movie->runtime);

    Livewire::test('media-search')
        ->set('query', 'Matrix')
        ->assertSee('1999')
        ->assertSee($runtime)
        ->assertSee('Action')
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

it('uses black active styling in search results', function () {
    Show::factory()->create(['name' => 'The Wire', 'language' => 'English']);

    Livewire::test('media-search')
        ->set('query', 'Wire')
        ->assertSee('data-active:!bg-black')
        ->assertSee('group-data-active/item:text-zinc-400');
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

it('renders network logo for show with known network id', function () {
    Show::factory()->create([
        'name' => 'Logo Network Show',
        'language' => 'English',
        'network' => ['id' => NetworkLogo::NbcUs->value, 'name' => 'NBC', 'country' => ['name' => 'United States']],
    ]);

    Livewire::test('media-search')
        ->set('query', 'Logo Network')
        ->assertSeeHtml('alt="NBC (US)"');
});

it('renders streaming logo for show with known web channel id', function () {
    Show::factory()->create([
        'name' => 'Logo Streaming Show',
        'language' => 'English',
        'web_channel' => ['id' => StreamingLogo::Netflix->value, 'name' => 'Netflix'],
    ]);

    Livewire::test('media-search')
        ->set('query', 'Logo Streaming')
        ->assertSeeHtml('alt="Netflix"');
});

it('falls back to text for show with unknown network id', function () {
    Show::factory()->create([
        'name' => 'Fallback Network Show',
        'language' => 'English',
        'network' => ['id' => 99999, 'name' => 'Obscure Network', 'country' => ['name' => 'Canada']],
    ]);

    Livewire::test('media-search')
        ->set('query', 'Fallback Network')
        ->assertSee('Obscure Network (Canada)')
        ->assertDontSeeHtml('alt="Obscure Network (Canada)"');
});
