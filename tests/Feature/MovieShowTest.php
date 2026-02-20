<?php

use App\Enums\Language;
use App\Enums\MovieStatus;
use App\Models\Movie;
use App\Models\User;
use App\Services\CartService;
use Livewire\Livewire;

beforeEach(function () {
    config(['scout.driver' => 'collection']);
});

it('requires authentication to view movie page', function () {
    $movie = Movie::factory()->create();

    $this->get(route('movies.show', $movie))
        ->assertRedirect(route('login'));
});

it('displays movie page for authenticated users', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'title' => 'The Matrix',
        'release_date' => '1999-03-31',
        'runtime' => 136,
        'genres' => ['Action', 'Sci-Fi'],
        'imdb_id' => 'tt0133093',
    ]);

    $this->actingAs($user)
        ->get(route('movies.show', $movie))
        ->assertSuccessful()
        ->assertSeeLivewire('movies.show');
});

it('displays movie title and release date', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'title' => 'Inception',
        'release_date' => '2010-07-16',
    ]);

    Livewire::actingAs($user)
        ->test('movies.show', ['movie' => $movie])
        ->assertSee('Inception')
        ->assertSee('07/16/10');
});

it('displays formatted runtime', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'runtime' => 148,
    ]);

    Livewire::actingAs($user)
        ->test('movies.show', ['movie' => $movie])
        ->assertSee('2h28m');
});

it('displays genres as badges', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'genres' => ['Action', 'Drama', 'Thriller'],
    ]);

    Livewire::actingAs($user)
        ->test('movies.show', ['movie' => $movie])
        ->assertSee('Action')
        ->assertSee('Drama')
        ->assertSee('Thriller');
});

it('displays IMDB link with correct URL', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'imdb_id' => 'tt0133093',
    ]);

    Livewire::actingAs($user)
        ->test('movies.show', ['movie' => $movie])
        ->assertSee('https://www.imdb.com/title/tt0133093/');
});

it('displays only original language when spoken languages match', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'original_language' => Language::English,
        'spoken_languages' => [
            ['iso_639_1' => 'en', 'english_name' => 'English', 'name' => 'English'],
        ],
    ]);

    Livewire::actingAs($user)
        ->test('movies.show', ['movie' => $movie])
        ->assertSee('English')
        ->assertDontSee('English (');
});

it('displays only primary language without spoken languages', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'original_language' => Language::English,
        'spoken_languages' => [
            ['iso_639_1' => 'en', 'english_name' => 'English', 'name' => 'English'],
            ['iso_639_1' => 'fr', 'english_name' => 'French', 'name' => 'Français'],
            ['iso_639_1' => 'es', 'english_name' => 'Spanish', 'name' => 'Español'],
        ],
    ]);

    Livewire::actingAs($user)
        ->test('movies.show', ['movie' => $movie])
        ->assertSee('English')
        ->assertDontSee('French')
        ->assertDontSee('Spanish');
});

it('displays original title when it differs from main title', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'title' => 'Spirited Away',
        'original_title' => '千と千尋の神隠し',
    ]);

    Livewire::actingAs($user)
        ->test('movies.show', ['movie' => $movie])
        ->assertSee('千と千尋の神隠し');
});

it('does not display original title when it matches main title', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'title' => 'The Dark Knight',
        'original_title' => 'The Dark Knight',
    ]);

    Livewire::actingAs($user)
        ->test('movies.show', ['movie' => $movie])
        ->assertDontSee('Originally');
});

it('returns 404 for non-existent movie', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('movies.show', ['movie' => 99999]))
        ->assertNotFound();
});

it('handles movie without genres', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'genres' => null,
    ]);

    Livewire::actingAs($user)
        ->test('movies.show', ['movie' => $movie])
        ->assertSuccessful();
});

it('handles movie without runtime', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'runtime' => null,
    ]);

    Livewire::actingAs($user)
        ->test('movies.show', ['movie' => $movie])
        ->assertSuccessful();
});

it('handles movie without release date', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'release_date' => null,
        'year' => null,
    ]);

    Livewire::actingAs($user)
        ->test('movies.show', ['movie' => $movie])
        ->assertSuccessful();
});

it('falls back to year when release date is null', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'release_date' => null,
        'year' => 2029,
    ]);

    Livewire::actingAs($user)
        ->test('movies.show', ['movie' => $movie])
        ->assertSee('2029');
});

it('displays status label for released movie', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->withTmdbData()->create([
        'release_date' => '2020-01-01',
        'status' => MovieStatus::Released->value,
    ]);

    Livewire::actingAs($user)
        ->test('movies.show', ['movie' => $movie])
        ->assertSuccessful()
        ->assertSee($movie->status->getLabel());
});

it('includes background image error fallback', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create(['imdb_id' => 'tt0000001']);

    $this->actingAs($user)
        ->get(route('movies.show', $movie))
        ->assertSuccessful()
        ->assertSee('onerror=', false);
});

it('displays content rating from US release dates', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'release_dates' => [
            [
                'iso_3166_1' => 'US',
                'release_dates' => [
                    ['type' => 3, 'release_date' => '1999-03-31T00:00:00.000Z', 'certification' => 'R', 'note' => '', 'iso_639_1' => '', 'descriptors' => []],
                ],
            ],
        ],
    ]);

    Livewire::actingAs($user)
        ->test('movies.show', ['movie' => $movie])
        ->assertSee('R');
});

it('does not display content rating when release dates are null', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'release_dates' => null,
    ]);

    Livewire::actingAs($user)
        ->test('movies.show', ['movie' => $movie])
        ->assertSuccessful();
});

it('does not display content rating when US entry has empty certification', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'release_dates' => [
            [
                'iso_3166_1' => 'US',
                'release_dates' => [
                    ['type' => 3, 'release_date' => '1999-03-31T00:00:00.000Z', 'certification' => '', 'note' => '', 'iso_639_1' => '', 'descriptors' => []],
                ],
            ],
        ],
    ]);

    Livewire::actingAs($user)
        ->test('movies.show', ['movie' => $movie])
        ->assertSuccessful()
        ->assertDontSeeHtml('<span>R</span>');
});

it('prefers theatrical certification over other release types', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'release_dates' => [
            [
                'iso_3166_1' => 'US',
                'release_dates' => [
                    ['type' => 4, 'release_date' => '2020-01-01T00:00:00.000Z', 'certification' => 'PG', 'note' => '', 'iso_639_1' => '', 'descriptors' => []],
                    ['type' => 3, 'release_date' => '1999-03-31T00:00:00.000Z', 'certification' => 'R', 'note' => '', 'iso_639_1' => '', 'descriptors' => []],
                ],
            ],
        ],
    ]);

    Livewire::actingAs($user)
        ->test('movies.show', ['movie' => $movie])
        ->assertSee('R');
});

it('handles movie without status', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'status' => null,
    ]);

    Livewire::actingAs($user)
        ->test('movies.show', ['movie' => $movie])
        ->assertSuccessful();
});

describe('cart', function () {
    beforeEach(function () {
        session()->flush();
    });

    it('can add movie to cart', function () {
        $user = User::factory()->create();
        $movie = Movie::factory()->create();

        Livewire::actingAs($user)
            ->test('movies.show', ['movie' => $movie])
            ->assertSet('inCart', false)
            ->call('toggleCart')
            ->assertSet('inCart', true)
            ->assertDispatched('cart-updated');

        expect(app(CartService::class)->has($movie->id))->toBeTrue();
    });

    it('can remove movie from cart', function () {
        $user = User::factory()->create();
        $movie = Movie::factory()->create();
        app(CartService::class)->toggleMovie($movie->id);

        Livewire::actingAs($user)
            ->test('movies.show', ['movie' => $movie])
            ->assertSet('inCart', true)
            ->call('toggleCart')
            ->assertSet('inCart', false)
            ->assertDispatched('cart-updated');

        expect(app(CartService::class)->has($movie->id))->toBeFalse();
    });

    it('disables cart for unreleased movie statuses', function (string $rawStatus) {
        $user = User::factory()->create();
        $movie = Movie::factory()->withTmdbData()->create([
            'status' => $rawStatus,
            'release_date' => now()->addYear(),
            'digital_release_date' => null,
            'release_dates' => [],
        ]);

        Livewire::actingAs($user)
            ->test('movies.show', ['movie' => $movie])
            ->assertSet('isCartDisabled', true);
    })->with([
        'Rumored',
        'Planned',
        'In Production',
        'Post Production',
    ]);

    it('disables cart for canceled movies', function () {
        $user = User::factory()->create();
        $movie = Movie::factory()->withTmdbData()->create([
            'status' => 'Canceled',
            'release_date' => null,
            'digital_release_date' => null,
            'release_dates' => [],
        ]);

        Livewire::actingAs($user)
            ->test('movies.show', ['movie' => $movie])
            ->assertSet('isCartDisabled', true);
    });

    it('enables cart for released movies', function () {
        $user = User::factory()->create();
        $movie = Movie::factory()->withTmdbData()->create([
            'status' => 'Released',
            'digital_release_date' => '2020-01-01',
            'release_dates' => [],
        ]);

        Livewire::actingAs($user)
            ->test('movies.show', ['movie' => $movie])
            ->assertSet('isCartDisabled', false);
    });

    it('enables cart for movies with null status', function () {
        $user = User::factory()->create();
        $movie = Movie::factory()->create(['status' => null]);

        Livewire::actingAs($user)
            ->test('movies.show', ['movie' => $movie])
            ->assertSet('isCartDisabled', false);
    });

    it('prevents toggling cart for unreleased movies', function () {
        $user = User::factory()->create();
        $movie = Movie::factory()->withTmdbData()->create([
            'status' => 'Planned',
            'release_date' => now()->addYear(),
            'digital_release_date' => null,
            'release_dates' => [],
        ]);

        Livewire::actingAs($user)
            ->test('movies.show', ['movie' => $movie])
            ->call('toggleCart')
            ->assertSet('inCart', false)
            ->assertNotDispatched('cart-updated');

        expect(app(CartService::class)->has($movie->id))->toBeFalse();
    });

    it('prevents toggling cart for canceled movies', function () {
        $user = User::factory()->create();
        $movie = Movie::factory()->withTmdbData()->create([
            'status' => 'Canceled',
            'release_date' => null,
            'digital_release_date' => null,
            'release_dates' => [],
        ]);

        Livewire::actingAs($user)
            ->test('movies.show', ['movie' => $movie])
            ->call('toggleCart')
            ->assertSet('inCart', false)
            ->assertNotDispatched('cart-updated');

        expect(app(CartService::class)->has($movie->id))->toBeFalse();
    });
});
