<?php

declare(strict_types=1);

use App\Exceptions\IptorrentsAuthException;
use App\Exceptions\IptorrentsRateLimitExceededException;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\Show;
use App\Services\IptorrentsService;
use App\Services\TorrentFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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

it('searches a season pack once when the whole season is requested', function () {
    $show = Show::factory()->create();
    $episodes = Episode::factory()->count(3)->for($show)->sequence(['number' => 1], ['number' => 2], ['number' => 3])->create(['season' => 2]);

    $ipt = $this->mock(IptorrentsService::class);
    $ipt->shouldReceive('searchSeasonPack')
        ->once()
        ->with(Mockery::type(Show::class), 2)
        ->andReturn(fulfillmentResult('Some.Show.S02.1080p.x265', 700));
    $ipt->shouldNotReceive('searchEpisodeByName');

    $result = (new TorrentFulfillmentService($ipt))->fulfill($episodes);

    expect($result->downloads)->toBe([
        ['torrent_id' => 700, 'filename' => 'Some.Show.S02.1080p.x265.torrent'],
    ])->and($result->covered)->toHaveCount(3);
});

it('falls back to per-episode search when the season pack misses', function () {
    $show = Show::factory()->create();
    $episodes = Episode::factory()->count(3)->for($show)->sequence(['number' => 1], ['number' => 2], ['number' => 3])->create(['season' => 1, 'airdate' => '2024-05-01', 'airtime' => '20:00']);

    $ipt = $this->mock(IptorrentsService::class);
    $ipt->shouldReceive('searchSeasonPack')->once()->andReturnNull();
    $ipt->shouldReceive('searchEpisodeByName')
        ->once()
        ->andReturn(fulfillmentResult('Some.Show.S01E01.1080p.x265', 800));

    $result = (new TorrentFulfillmentService($ipt))->fulfill($episodes);

    expect($result->downloads)->toBe([
        ['torrent_id' => 800, 'filename' => 'Some.Show.S01E01.1080p.x265.torrent'],
    ])->and($result->covered)->toHaveCount(3);
});

it('never searches a season pack for a partial season', function () {
    $show = Show::factory()->create();
    Episode::factory()->count(3)->for($show)->sequence(['number' => 1], ['number' => 2], ['number' => 3])->create(['season' => 1]);

    $requested = $show->episodes()->orderBy('number')->take(2)->get();

    $ipt = $this->mock(IptorrentsService::class);
    $ipt->shouldNotReceive('searchSeasonPack');
    $ipt->shouldReceive('searchEpisodeByName')->andReturn(fulfillmentResult('Ep', 1));

    (new TorrentFulfillmentService($ipt))->fulfill($requested);
});

it('never searches a season pack for a lone requested episode', function () {
    $show = Show::factory()->create();
    Episode::factory()->count(3)->for($show)->sequence(['number' => 1], ['number' => 2], ['number' => 3])->create(['season' => 1]);

    $requested = $show->episodes()->orderBy('number')->take(1)->get();

    $ipt = $this->mock(IptorrentsService::class);
    $ipt->shouldNotReceive('searchSeasonPack');
    $ipt->shouldReceive('searchEpisodeByName')
        ->once()
        ->andReturn(fulfillmentResult('Lone.S01E01', 900));

    $result = (new TorrentFulfillmentService($ipt))->fulfill($requested);

    expect($result->covered)->toHaveCount(1);
});

it('never searches a season pack for specials', function () {
    $show = Show::factory()->create();
    $episodes = Episode::factory()->count(2)->for($show)->sequence(['number' => 1], ['number' => 2])->create(['season' => 0]);

    $ipt = $this->mock(IptorrentsService::class);
    $ipt->shouldNotReceive('searchSeasonPack');
    $ipt->shouldReceive('searchEpisodeByName')->andReturn(fulfillmentResult('Special', 1));

    (new TorrentFulfillmentService($ipt))->fulfill($episodes);
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
