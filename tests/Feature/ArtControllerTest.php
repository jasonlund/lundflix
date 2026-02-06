<?php

use App\Jobs\StoreFanart;
use App\Models\Movie;
use App\Models\Show;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Http::preventStrayRequests();
    Cache::flush();
    $this->actingAs(User::factory()->create());
});

it('redirects to active media url when media exists', function () {
    $movie = Movie::factory()->create();

    $movie->media()->create([
        'fanart_id' => '12345',
        'type' => 'hdmovielogo',
        'url' => 'https://assets.fanart.tv/fanart/movies/278/hdmovielogo/cached.png',
        'path' => "fanart/movie/{$movie->id}/hdmovielogo/12345.png",
        'likes' => 5,
        'is_active' => true,
    ]);

    $response = $this->get("/art/movie/{$movie->id}/logo");

    $response->assertRedirect('https://assets.fanart.tv/fanart/movies/278/hdmovielogo/cached.png');
    Http::assertNothingSent();
});

it('redirects to preview url when preview is requested', function () {
    $movie = Movie::factory()->create();

    $movie->media()->create([
        'fanart_id' => '12345',
        'type' => 'movieposter',
        'url' => 'https://assets.fanart.tv/fanart/movies/278/movieposter/cached.jpg',
        'path' => null,
        'likes' => 5,
        'is_active' => true,
    ]);

    $response = $this->get("/art/movie/{$movie->id}/poster?preview=1");

    $response->assertRedirect('https://assets.fanart.tv/preview/movies/278/movieposter/cached.jpg');
    Http::assertNothingSent();
});

it('preserves query string and fragment when generating preview url', function () {
    $movie = Movie::factory()->create();

    $movie->media()->create([
        'fanart_id' => '12346',
        'type' => 'movieposter',
        'url' => 'https://assets.fanart.tv/fanart/movies/278/movieposter/cached.jpg?token=abc#section',
        'path' => null,
        'likes' => 5,
        'is_active' => true,
    ]);

    $response = $this->get("/art/movie/{$movie->id}/poster?preview=1");

    $response->assertRedirect('https://assets.fanart.tv/preview/movies/278/movieposter/cached.jpg?token=abc#section');
    Http::assertNothingSent();
});

it('fetches from api and redirects when media does not exist for movie', function () {
    Queue::fake();

    $movie = Movie::factory()->create(['imdb_id' => 'tt0111161']);

    Http::fake([
        'webservice.fanart.tv/v3/movies/tt0111161' => Http::response([
            'hdmovielogo' => [
                ['id' => '12345', 'url' => 'https://assets.fanart.tv/fanart/movies/278/hdmovielogo/fresh.png', 'lang' => 'en', 'likes' => '5'],
            ],
        ]),
    ]);

    $response = $this->get("/art/movie/{$movie->id}/logo");

    $response->assertRedirect('https://assets.fanart.tv/fanart/movies/278/hdmovielogo/fresh.png');
    Queue::assertPushed(StoreFanart::class, fn ($job) => $job->model->id === $movie->id);
});

it('fetches from api and redirects when media does not exist for show', function () {
    Queue::fake();

    $show = Show::factory()->create([
        'thetvdb_id' => 264492,
    ]);

    Http::fake([
        'webservice.fanart.tv/v3/tv/264492' => Http::response([
            'tvposter' => [
                ['id' => '67890', 'url' => 'https://assets.fanart.tv/fanart/tv/264492/tvposter/fresh.jpg', 'lang' => 'en', 'likes' => '10'],
            ],
        ]),
    ]);

    $response = $this->get("/art/show/{$show->id}/poster");

    $response->assertRedirect('https://assets.fanart.tv/fanart/tv/264492/tvposter/fresh.jpg');
    Queue::assertPushed(StoreFanart::class, fn ($job) => $job->model->id === $show->id);
});

it('returns 404 when api returns no artwork', function () {
    Queue::fake();

    $movie = Movie::factory()->create(['imdb_id' => 'tt9999999']);
    $start = now();
    $missingCacheKey = "fanart:missing:movie:{$movie->id}";

    Http::fake([
        'webservice.fanart.tv/v3/movies/tt9999999' => Http::response([], 404),
    ]);

    $response = $this->get("/art/movie/{$movie->id}/logo");

    $response->assertNotFound();
    expect(Cache::has($missingCacheKey))->toBeTrue();

    $this->travelTo($start->copy()->addHours(167));
    expect(Cache::has($missingCacheKey))->toBeTrue();

    $this->travelTo($start->copy()->addHours(169));
    expect(Cache::has($missingCacheKey))->toBeFalse();

    $this->travelBack();
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

    $response = $this->get("/art/movie/{$movie->id}/logo");

    $response->assertNotFound();
    expect(Cache::has("fanart:missing:movie:{$movie->id}:logo"))->toBeTrue();
    Queue::assertNothingPushed();
});

it('returns 404 when requested type is an empty array', function () {
    Queue::fake();

    $movie = Movie::factory()->create(['imdb_id' => 'tt0111161']);

    Http::fake([
        'webservice.fanart.tv/v3/movies/tt0111161' => Http::response([
            'name' => 'The Shawshank Redemption',
            'tmdb_id' => '278',
            'imdb_id' => 'tt0111161',
            'hdmovielogo' => [],
        ]),
    ]);

    $response = $this->get("/art/movie/{$movie->id}/logo");

    $response->assertNotFound();
    expect(Cache::has("fanart:missing:movie:{$movie->id}:logo"))->toBeTrue();
    Queue::assertNothingPushed();
});

it('falls back to clear art when logo is missing', function () {
    Queue::fake();

    $movie = Movie::factory()->create(['imdb_id' => 'tt0111161']);

    Http::fake([
        'webservice.fanart.tv/v3/movies/tt0111161' => Http::response([
            'hdmovieclearart' => [
                ['id' => '12345', 'url' => 'https://assets.fanart.tv/fanart/movies/278/hdmovieclearart/clear.png', 'lang' => 'en', 'likes' => '2'],
            ],
            'moviethumb' => [
                ['id' => '67890', 'url' => 'https://assets.fanart.tv/fanart/movies/278/moviethumb/thumb.jpg', 'lang' => 'en', 'likes' => '10'],
            ],
        ]),
    ]);

    $response = $this->get("/art/movie/{$movie->id}/logo");

    $response->assertRedirect('https://assets.fanart.tv/fanart/movies/278/hdmovieclearart/clear.png');
    Queue::assertPushed(StoreFanart::class, fn ($job) => $job->model->id === $movie->id);
});

it('ignores 4k backgrounds when requesting background', function () {
    Queue::fake();

    $movie = Movie::factory()->create(['imdb_id' => 'tt0111161']);

    Http::fake([
        'webservice.fanart.tv/v3/movies/tt0111161' => Http::response([
            'moviebackground-4k' => [
                ['id' => '12345', 'url' => 'https://assets.fanart.tv/fanart/movies/278/moviebackground-4k/bg.jpg', 'lang' => 'en', 'likes' => '5'],
            ],
        ]),
    ]);

    $response = $this->get("/art/movie/{$movie->id}/background");

    $response->assertNotFound();
    expect(Cache::has("fanart:missing:movie:{$movie->id}:background"))->toBeTrue();
    Queue::assertNothingPushed();
});

it('returns 404 when movie does not exist', function () {
    $response = $this->get('/art/movie/99999/logo');

    $response->assertNotFound();
});

it('returns 404 when show does not exist', function () {
    $response = $this->get('/art/show/99999/poster');

    $response->assertNotFound();
});

it('returns 404 for invalid mediable type', function () {
    $response = $this->get('/art/episode/1/logo');

    $response->assertNotFound();
});

it('returns 404 for unsupported art type', function () {
    $movie = Movie::factory()->create();

    $response = $this->get("/art/movie/{$movie->id}/hdmovielogo");

    $response->assertNotFound();
});

it('returns 404 when show has no thetvdb_id', function () {
    Queue::fake();

    $show = Show::factory()->create([
        'thetvdb_id' => null,
    ]);

    $response = $this->get("/art/show/{$show->id}/poster");

    $response->assertNotFound();
    Queue::assertNothingPushed();
});

it('returns 404 when image url is missing', function () {
    Queue::fake();

    $movie = Movie::factory()->create(['imdb_id' => 'tt0111161']);

    Http::fake([
        'webservice.fanart.tv/v3/movies/tt0111161' => Http::response([
            'hdmovielogo' => [
                ['id' => '12345', 'lang' => 'en', 'likes' => '5'],
            ],
        ]),
    ]);

    $response = $this->get("/art/movie/{$movie->id}/logo");

    $response->assertNotFound();
    expect(Cache::has("fanart:missing:movie:{$movie->id}:logo"))->toBeTrue();
    Queue::assertPushed(StoreFanart::class);
});

it('skips api request when missing artwork is cached', function () {
    Queue::fake();

    $movie = Movie::factory()->create(['imdb_id' => 'tt9999999']);

    Http::fake([
        'webservice.fanart.tv/v3/movies/tt9999999' => Http::response([], 404),
    ]);

    $this->get("/art/movie/{$movie->id}/logo")->assertNotFound();
    Http::assertSentCount(1);

    $this->get("/art/movie/{$movie->id}/logo")->assertNotFound();
    Http::assertSentCount(1);
    Queue::assertNothingPushed();
});

it('returns 404 and caches error for 12 hours when api returns server error', function () {
    Exceptions::fake();
    Queue::fake();

    $movie = Movie::factory()->create(['imdb_id' => 'tt0111161']);
    $start = now();
    $missingCacheKey = "fanart:missing:movie:{$movie->id}";

    Http::fake([
        'webservice.fanart.tv/v3/movies/tt0111161' => Http::response([], 500),
    ]);

    $response = $this->get("/art/movie/{$movie->id}/logo");

    $response->assertNotFound();
    Exceptions::assertReported(RequestException::class);
    expect(Cache::has($missingCacheKey))->toBeTrue();

    $this->travelTo($start->copy()->addHours(11));
    expect(Cache::has($missingCacheKey))->toBeTrue();

    $this->travelTo($start->copy()->addHours(13));
    expect(Cache::has($missingCacheKey))->toBeFalse();

    $this->travelBack();
    Queue::assertNothingPushed();
});

it('returns 404 and caches error for 12 hours when api times out', function () {
    Exceptions::fake();
    Queue::fake();

    $movie = Movie::factory()->create(['imdb_id' => 'tt0111161']);
    $start = now();
    $missingCacheKey = "fanart:missing:movie:{$movie->id}";

    Http::fake([
        'webservice.fanart.tv/v3/movies/tt0111161' => fn () => throw new ConnectionException('Connection timed out'),
    ]);

    $response = $this->get("/art/movie/{$movie->id}/logo");

    $response->assertNotFound();
    Exceptions::assertReported(ConnectionException::class);
    expect(Cache::has($missingCacheKey))->toBeTrue();

    $this->travelTo($start->copy()->addHours(11));
    expect(Cache::has($missingCacheKey))->toBeTrue();

    $this->travelTo($start->copy()->addHours(13));
    expect(Cache::has($missingCacheKey))->toBeFalse();

    $this->travelBack();
    Queue::assertNothingPushed();
});

it('redirects guests to login', function () {
    auth()->logout();

    $movie = Movie::factory()->create();

    $this->get("/art/movie/{$movie->id}/logo")
        ->assertRedirect(route('login'));
});
