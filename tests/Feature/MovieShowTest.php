<?php

use App\Models\Movie;
use App\Models\User;
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
        'year' => 1999,
        'runtime' => 136,
        'genres' => 'Action,Sci-Fi',
        'num_votes' => 1500000,
        'imdb_id' => 'tt0133093',
    ]);

    $this->actingAs($user)
        ->get(route('movies.show', $movie))
        ->assertSuccessful()
        ->assertSeeLivewire('movies.show');
});

it('displays movie title and year', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'title' => 'Inception',
        'year' => 2010,
    ]);

    Livewire::actingAs($user)
        ->test('movies.show', ['movie' => $movie])
        ->assertSee('Inception')
        ->assertSee('2010');
});

it('displays formatted runtime', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'runtime' => 148,
    ]);

    Livewire::actingAs($user)
        ->test('movies.show', ['movie' => $movie])
        ->assertSee('2h 28m');
});

it('displays vote count formatted with commas', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'num_votes' => 2500000,
    ]);

    Livewire::actingAs($user)
        ->test('movies.show', ['movie' => $movie])
        ->assertSee('2,500,000 votes');
});

it('displays genres as badges', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'genres' => 'Action,Drama,Thriller',
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

it('handles movie without vote count', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'num_votes' => null,
    ]);

    Livewire::actingAs($user)
        ->test('movies.show', ['movie' => $movie])
        ->assertSuccessful()
        ->assertDontSee('votes');
});
