<?php

declare(strict_types=1);

use App\Enums\TorrentSearchStrategy;
use App\Exceptions\IptorrentsAuthException;
use App\Exceptions\IptorrentsRateLimitExceededException;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\Show;
use App\Services\IptorrentsService;
use App\Services\TorrentFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(fn () => resetIptThrottle());

function fulfillmentResult(string $name, int $torrentId = 1): array
{
    $filename = str_replace(' ', '.', $name);

    return [
        'torrent_id' => $torrentId,
        'name' => $name,
        'size' => '1.5 GB',
        'seeders' => 50,
        'leechers' => 5,
        'snatches' => 100,
        'uploaded' => '2024-01-01',
        'download_url' => "https://iptorrents.com/download.php/{$torrentId}/{$filename}.torrent",
    ];
}

it('searches every requested episode individually and downloads each match', function () {
    $show = Show::factory()->create();
    $episodes = Episode::factory()->count(4)->for($show)
        ->sequence(['number' => 1], ['number' => 2], ['number' => 3], ['number' => 4])
        ->create(['season' => 3]);

    $ipt = $this->mock(IptorrentsService::class);
    $ipt->shouldNotReceive('searchSeasonPack');
    foreach (range(1, 4) as $num) {
        $ipt->shouldReceive('searchEpisodeByName')
            ->once()
            ->withArgs(fn (Episode $e): bool => $e->number === $num)
            ->andReturn(fulfillmentResult("Some.Show.S03E0{$num}.1080p.x265", 800 + $num));
    }

    $result = (new TorrentFulfillmentService($ipt))->fulfill($episodes);

    expect($result->downloads)->toBe([
        ['torrent_id' => 801, 'filename' => 'Some.Show.S03E01.1080p.x265.torrent'],
        ['torrent_id' => 802, 'filename' => 'Some.Show.S03E02.1080p.x265.torrent'],
        ['torrent_id' => 803, 'filename' => 'Some.Show.S03E03.1080p.x265.torrent'],
        ['torrent_id' => 804, 'filename' => 'Some.Show.S03E04.1080p.x265.torrent'],
    ])->and($result->covered)->toHaveCount(4);
});

it('only covers episodes that have a torrent', function () {
    $show = Show::factory()->create();
    $episodes = Episode::factory()->count(3)->for($show)
        ->sequence(['number' => 1], ['number' => 2], ['number' => 3])
        ->create(['season' => 1]);

    $ipt = $this->mock(IptorrentsService::class);
    $ipt->shouldReceive('searchEpisodeByName')
        ->times(3)
        ->andReturnUsing(fn (Episode $e): ?array => $e->number === 2
            ? null
            : fulfillmentResult("Some.Show.S01E0{$e->number}.1080p.x265", 900 + $e->number));

    $result = (new TorrentFulfillmentService($ipt))->fulfill($episodes);

    expect($result->downloads)->toBe([
        ['torrent_id' => 901, 'filename' => 'Some.Show.S01E01.1080p.x265.torrent'],
        ['torrent_id' => 903, 'filename' => 'Some.Show.S01E03.1080p.x265.torrent'],
    ])->and($result->covered)->toHaveCount(2);
});

it('logs the found torrent and the media it fulfills', function () {
    Log::spy();

    $movie = Movie::factory()->create(['title' => 'Dune', 'year' => 2024]);

    $ipt = $this->mock(IptorrentsService::class);
    $ipt->shouldReceive('searchMovieByName')->andReturn(fulfillmentResult('Dune.2024.x265', 5));

    (new TorrentFulfillmentService($ipt))->fulfill(collect([$movie]));

    Log::shouldHaveReceived('info')
        ->withArgs(function (string $message, array $context) use ($movie): bool {
            return $message === 'Torrent found'
                && $context['torrent']['id'] === 5
                && $context['torrent']['name'] === 'Dune.2024.x265'
                && $context['fulfills'] === [[
                    'type' => 'movie',
                    'id' => $movie->id,
                    'title' => 'Dune',
                    'year' => 2024,
                ]];
        })
        ->once();
});

it('logs each episode it finds a torrent for', function () {
    Log::spy();

    $show = Show::factory()->create(['name' => 'Some Show']);
    $episodes = Episode::factory()->count(2)->for($show)
        ->sequence(['number' => 1], ['number' => 2])
        ->create(['season' => 4]);

    $ipt = $this->mock(IptorrentsService::class);
    $ipt->shouldReceive('searchEpisodeByName')
        ->andReturnUsing(fn (Episode $e): array => fulfillmentResult("Some.Show.S04E0{$e->number}.x265", 9));

    (new TorrentFulfillmentService($ipt))->fulfill($episodes);

    Log::shouldHaveReceived('info')
        ->withArgs(function (string $message, array $context): bool {
            return $message === 'Torrent found'
                && count($context['fulfills']) === 1
                && $context['fulfills'][0]['type'] === 'episode'
                && $context['fulfills'][0]['show'] === 'Some Show'
                && $context['fulfills'][0]['code'] === 'S04E01';
        })
        ->once();
});

it('logs a warning when no torrent is found', function () {
    Log::spy();

    $movie = Movie::factory()->create();

    $ipt = $this->mock(IptorrentsService::class);
    $ipt->shouldReceive('searchMovieByName')->andReturnNull();

    (new TorrentFulfillmentService($ipt))->fulfill(collect([$movie]));

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context): bool => $message === 'No torrent found'
            && $context['media'][0]['id'] === $movie->id)
        ->once();
});

it('logs a warning and skips the group on an unexpected error', function () {
    Log::spy();

    $show = Show::factory()->create();
    Episode::factory()->count(2)->for($show)
        ->sequence(['number' => 1], ['number' => 2])
        ->create(['season' => 1]);

    $requested = $show->episodes()->take(1)->get();

    $ipt = $this->mock(IptorrentsService::class);
    $ipt->shouldReceive('searchEpisodeByName')->andThrow(new RuntimeException('boom'));

    (new TorrentFulfillmentService($ipt))->fulfill($requested);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context): bool => $message === 'Torrent fulfillment failed'
            && $context['error'] === 'boom')
        ->once();
});

it('logs a warning when the run is aborted by a rate limit', function () {
    Log::spy();

    $movie = Movie::factory()->create();

    $ipt = $this->mock(IptorrentsService::class);
    $ipt->shouldReceive('searchMovieByName')->andThrow(new IptorrentsRateLimitExceededException);

    try {
        (new TorrentFulfillmentService($ipt))->fulfill(collect([$movie]));
    } catch (IptorrentsRateLimitExceededException) {
        // expected
    }

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context): bool => $message === 'Torrent fulfillment aborted'
            && $context['reason'] === 'IptorrentsRateLimitExceededException')
        ->once();
});

it('searches movies by name and marks them covered', function () {
    $movies = Movie::factory()->count(2)->create();

    $ipt = $this->mock(IptorrentsService::class);
    $ipt->shouldReceive('searchMovieByName')
        ->twice()
        ->andReturn(
            fulfillmentResult('Movie.One.2024.x265', 10),
            fulfillmentResult('Movie.Two.2024.x265', 11),
        );

    $result = (new TorrentFulfillmentService($ipt))->fulfill($movies);

    expect($result->downloads)->toHaveCount(2)
        ->and($result->covered)->toHaveCount(2);
});

it('skips a failing movie and continues with the rest', function () {
    $movies = Movie::factory()->count(2)->create();
    $failing = $movies->first();

    $ipt = $this->mock(IptorrentsService::class);
    $ipt->shouldReceive('searchMovieByName')
        ->andReturnUsing(fn (Movie $movie): array => $movie->id === $failing->id
            ? throw new RuntimeException('boom')
            : fulfillmentResult('Movie.Two.2024.x265', 50));

    $result = (new TorrentFulfillmentService($ipt))->fulfill($movies);

    expect($result->downloads)->toBe([
        ['torrent_id' => 50, 'filename' => 'Movie.Two.2024.x265.torrent'],
    ])->and($result->covered)->toHaveCount(1);
});

it('propagates rate-limit exceptions', function () {
    $movie = Movie::factory()->create();

    $ipt = $this->mock(IptorrentsService::class);
    $ipt->shouldReceive('searchMovieByName')->andThrow(new IptorrentsRateLimitExceededException);

    (new TorrentFulfillmentService($ipt))->fulfill(collect([$movie]));
})->throws(IptorrentsRateLimitExceededException::class);

it('propagates auth exceptions', function () {
    $movie = Movie::factory()->create();

    $ipt = $this->mock(IptorrentsService::class);
    $ipt->shouldReceive('searchMovieByName')->andThrow(new IptorrentsAuthException);

    (new TorrentFulfillmentService($ipt))->fulfill(collect([$movie]));
})->throws(IptorrentsAuthException::class);

it('skips a failing group and continues with the rest', function () {
    $showA = Show::factory()->create();
    $showB = Show::factory()->create();
    Episode::factory()->count(3)->for($showA)->sequence(['number' => 1], ['number' => 2], ['number' => 3])->create(['season' => 1]);
    Episode::factory()->count(3)->for($showB)->sequence(['number' => 1], ['number' => 2], ['number' => 3])->create(['season' => 1]);

    $requested = $showA->episodes()->take(1)->get()
        ->merge($showB->episodes()->take(1)->get());

    $ipt = $this->mock(IptorrentsService::class);
    $ipt->shouldReceive('searchEpisodeByName')
        ->andReturnUsing(fn (Episode $episode): array => $episode->show_id === $showA->id
            ? throw new RuntimeException('boom')
            : fulfillmentResult('Show.B.S01E01', 50));

    $result = (new TorrentFulfillmentService($ipt))->fulfill($requested);

    expect($result->downloads)->toBe([
        ['torrent_id' => 50, 'filename' => 'Show.B.S01E01.torrent'],
    ])->and($result->covered)->toHaveCount(1);
});

it('searches by name for movies and episodes under the default strategy', function () {
    $movie = Movie::factory()->create();
    $show = Show::factory()->create();
    Episode::factory()->for($show)->create(['season' => 1, 'number' => 1]);
    $episode = $show->episodes()->first();

    $ipt = $this->mock(IptorrentsService::class);
    $ipt->shouldReceive('searchMovieByName')->once()->andReturn(fulfillmentResult('Movie.x265', 1));
    $ipt->shouldReceive('searchEpisodeByName')->once()->andReturn(fulfillmentResult('Show.S01E01.x265', 2));
    $ipt->shouldNotReceive('searchMovie');
    $ipt->shouldNotReceive('searchEpisode');

    $result = (new TorrentFulfillmentService($ipt))->fulfill(collect([$movie, $episode]));

    expect($result->covered)->toHaveCount(2);
});

it('searches by imdb id for movies and episodes under the imdb strategy', function () {
    $movie = Movie::factory()->create();
    $show = Show::factory()->create();
    Episode::factory()->for($show)->create(['season' => 1, 'number' => 1]);
    $episode = $show->episodes()->first();

    $ipt = $this->mock(IptorrentsService::class);
    $ipt->shouldReceive('searchMovie')->once()->andReturn(fulfillmentResult('Movie.x265', 1));
    $ipt->shouldReceive('searchEpisode')->once()->andReturn(fulfillmentResult('Show.S01E01.x265', 2));
    $ipt->shouldNotReceive('searchMovieByName');
    $ipt->shouldNotReceive('searchEpisodeByName');

    $result = (new TorrentFulfillmentService($ipt))->fulfill(collect([$movie, $episode]), TorrentSearchStrategy::ImdbId);

    expect($result->covered)->toHaveCount(2);
});
