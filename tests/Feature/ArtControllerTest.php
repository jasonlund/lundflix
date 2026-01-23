<?php

use App\Jobs\StoreFanart;
use App\Models\Movie;
use App\Models\Show;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Http::preventStrayRequests();
    $this->actingAs(User::factory()->create());
});

it('redirects to cached media url when media exists', function () {
    $movie = Movie::factory()->create();
    $movie->media()->create([
        'fanart_id' => '12345',
        'type' => 'hdmovielogo',
        'url' => 'https://assets.fanart.tv/fanart/movies/278/hdmovielogo/cached.png',
        'likes' => 5,
    ]);

    $response = $this->get("/art/movie/{$movie->id}/hdmovielogo");

    $response->assertRedirect('https://assets.fanart.tv/fanart/movies/278/hdmovielogo/cached.png');
    Http::assertNothingSent();
});

it('fetches from api and redirects when media does not exist for movie', function () {
    Queue::fake();

    $movie = Movie::factory()->create(['imdb_id' => 'tt0111161']);

    Http::fake([
        'webservice.fanart.tv/v3/movies/tt0111161' => Http::response([
            'name' => 'The Shawshank Redemption',
            'tmdb_id' => '278',
            'imdb_id' => 'tt0111161',
            'hdmovielogo' => [
                ['id' => '12345', 'url' => 'https://assets.fanart.tv/fanart/movies/278/hdmovielogo/fresh.png', 'lang' => 'en', 'likes' => '5'],
            ],
        ]),
    ]);

    $response = $this->get("/art/movie/{$movie->id}/hdmovielogo");

    $response->assertRedirect('https://assets.fanart.tv/fanart/movies/278/hdmovielogo/fresh.png');
    Queue::assertPushed(StoreFanart::class, fn ($job) => $job->model->id === $movie->id);
});

it('fetches from api and redirects when media does not exist for show', function () {
    Queue::fake();

    $show = Show::factory()->create([
        'externals' => ['thetvdb' => 264492],
    ]);

    Http::fake([
        'webservice.fanart.tv/v3/tv/264492' => Http::response([
            'name' => 'Under the Dome',
            'thetvdb_id' => '264492',
            'tvposter' => [
                ['id' => '67890', 'url' => 'https://assets.fanart.tv/fanart/tv/264492/tvposter/fresh.jpg', 'lang' => 'en', 'likes' => '10'],
            ],
        ]),
    ]);

    $response = $this->get("/art/show/{$show->id}/tvposter");

    $response->assertRedirect('https://assets.fanart.tv/fanart/tv/264492/tvposter/fresh.jpg');
    Queue::assertPushed(StoreFanart::class, fn ($job) => $job->model->id === $show->id);
});

it('returns 404 when api returns no artwork', function () {
    Queue::fake();

    $movie = Movie::factory()->create(['imdb_id' => 'tt9999999']);

    Http::fake([
        'webservice.fanart.tv/v3/movies/tt9999999' => Http::response([], 404),
    ]);

    $response = $this->get("/art/movie/{$movie->id}/hdmovielogo");

    $response->assertNotFound();
    Queue::assertNothingPushed();
});

it('returns 404 when requested type is not in api response', function () {
    Queue::fake();

    $movie = Movie::factory()->create(['imdb_id' => 'tt0111161']);

    Http::fake([
        'webservice.fanart.tv/v3/movies/tt0111161' => Http::response([
            'name' => 'The Shawshank Redemption',
            'tmdb_id' => '278',
            'imdb_id' => 'tt0111161',
            'movieposter' => [
                ['id' => '12345', 'url' => 'https://assets.fanart.tv/poster.jpg', 'lang' => 'en', 'likes' => '5'],
            ],
        ]),
    ]);

    $response = $this->get("/art/movie/{$movie->id}/hdmovielogo");

    $response->assertNotFound();
    Queue::assertNothingPushed();
});

it('returns 404 when movie does not exist', function () {
    $response = $this->get('/art/movie/99999/hdmovielogo');

    $response->assertNotFound();
});

it('returns 404 when show does not exist', function () {
    $response = $this->get('/art/show/99999/tvposter');

    $response->assertNotFound();
});

it('returns 404 for invalid mediable type', function () {
    $response = $this->get('/art/episode/1/hdmovielogo');

    $response->assertNotFound();
});

it('redirects guests to login', function () {
    auth()->logout();

    $movie = Movie::factory()->create();

    $this->get("/art/movie/{$movie->id}/hdmovielogo")
        ->assertRedirect(route('login'));
});
