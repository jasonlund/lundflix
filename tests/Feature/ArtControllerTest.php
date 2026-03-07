<?php

use App\Enums\ArtworkType;
use App\Models\Movie;
use App\Models\Show;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('redirects to tmdb cdn url when active media exists', function () {
    $movie = Movie::factory()->create();

    $movie->media()->create([
        'file_path' => '/abc123.jpg',
        'type' => ArtworkType::Logo->value,
        'vote_average' => 5.5,
        'vote_count' => 10,
        'is_active' => true,
    ]);

    $this->get("/art/movie/{$movie->sqid}/logo")
        ->assertRedirect('https://image.tmdb.org/t/p/w500/abc123.jpg');
});

it('returns 404 when no active media exists', function () {
    $movie = Movie::factory()->create();

    $movie->media()->create([
        'file_path' => '/abc123.jpg',
        'type' => ArtworkType::Logo->value,
        'vote_average' => 5.5,
        'vote_count' => 10,
        'is_active' => false,
    ]);

    $this->get("/art/movie/{$movie->sqid}/logo")
        ->assertNotFound();
});

it('redirects to correct size per type', function (string $type, ArtworkType $artworkType, string $expectedSize) {
    $movie = Movie::factory()->create();

    $movie->media()->create([
        'file_path' => '/test.jpg',
        'type' => $artworkType->value,
        'vote_average' => 5.0,
        'vote_count' => 1,
        'is_active' => true,
    ]);

    $this->get("/art/movie/{$movie->sqid}/{$type}")
        ->assertRedirect("https://image.tmdb.org/t/p/{$expectedSize}/test.jpg");
})->with([
    'logo' => ['logo', ArtworkType::Logo, 'w500'],
    'poster' => ['poster', ArtworkType::Poster, 'w780'],
    'background' => ['background', ArtworkType::Backdrop, 'w1280'],
]);

it('works for shows', function () {
    $show = Show::factory()->create();

    $show->media()->create([
        'file_path' => '/show_poster.jpg',
        'type' => ArtworkType::Poster->value,
        'vote_average' => 7.0,
        'vote_count' => 25,
        'is_active' => true,
    ]);

    $this->get("/art/show/{$show->sqid}/poster")
        ->assertRedirect('https://image.tmdb.org/t/p/w780/show_poster.jpg');
});

it('returns 404 when movie does not exist', function () {
    $this->get('/art/movie/99999/logo')->assertNotFound();
});

it('returns 404 when show does not exist', function () {
    $this->get('/art/show/99999/poster')->assertNotFound();
});

it('returns 404 for invalid mediable type', function () {
    $this->get('/art/episode/1/logo')->assertNotFound();
});

it('returns 404 for unsupported art type', function () {
    $movie = Movie::factory()->create();

    $this->get("/art/movie/{$movie->sqid}/hdmovielogo")->assertNotFound();
});

it('returns 404 when model has no tmdb id', function () {
    $show = Show::factory()->create(['tmdb_id' => null]);

    $this->get("/art/show/{$show->sqid}/poster")->assertNotFound();
});

it('uses custom size when valid size query param is provided', function () {
    $movie = Movie::factory()->create();

    $movie->media()->create([
        'file_path' => '/abc123.jpg',
        'type' => ArtworkType::Logo->value,
        'vote_average' => 5.5,
        'vote_count' => 10,
        'is_active' => true,
    ]);

    $this->get("/art/movie/{$movie->sqid}/logo?size=w200")
        ->assertRedirect('https://image.tmdb.org/t/p/w200/abc123.jpg');
});

it('ignores invalid size query param and uses default', function () {
    $movie = Movie::factory()->create();

    $movie->media()->create([
        'file_path' => '/abc123.jpg',
        'type' => ArtworkType::Logo->value,
        'vote_average' => 5.5,
        'vote_count' => 10,
        'is_active' => true,
    ]);

    $this->get("/art/movie/{$movie->sqid}/logo?size=w9999")
        ->assertRedirect('https://image.tmdb.org/t/p/w500/abc123.jpg');
});

it('redirects guests to login', function () {
    auth()->logout();

    $movie = Movie::factory()->create();

    $this->get("/art/movie/{$movie->sqid}/logo")
        ->assertRedirect(route('login'));
});
