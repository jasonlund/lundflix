<?php

use App\Models\Movie;
use App\Services\Torrent\Finders\MovieByImdbFinder;
use App\Services\Torrent\Kind;
use App\Services\Torrent\TorrentRequest;
use App\Settings\IptorrentsSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    Http::preventStrayRequests();
    RateLimiter::clear('iptorrents');

    $settings = app(IptorrentsSettings::class);
    $settings->ipt_uid = '123';
    $settings->ipt_pass = 'abc';
    $settings->save();
});

it('returns top fitting result for IMDB query', function () {
    $movie = Movie::factory()->create(['imdb_id' => 'tt1111111', 'title' => 'Test', 'year' => 2024]);

    Http::fake([
        'iptorrents.com/*' => Http::response(fakeIptSearchHtml([
            fakeIptTorrentRow(torrentId: 1, name: 'Test.2024.1080p', size: '8 GB'),
        ])),
    ]);

    $finder = app(MovieByImdbFinder::class);
    $result = $finder->find(new TorrentRequest(Kind::Movie, $movie, 15_000_000_000));

    expect($result['torrent_id'])->toBe(1);
    Http::assertSent(fn ($r) => str_contains($r->url(), 'q=tt1111111'));
});

it('skips oversize first result and picks smaller fitting next', function () {
    $movie = Movie::factory()->create(['imdb_id' => 'tt1111111', 'title' => 'Test', 'year' => 2024]);

    Http::fake([
        'iptorrents.com/*' => Http::response(fakeIptSearchHtml([
            fakeIptTorrentRow(torrentId: 1, name: 'Test.2024.4K', size: '50 GB', seeders: 100),
            fakeIptTorrentRow(torrentId: 2, name: 'Test.2024.1080p', size: '8 GB', seeders: 50),
        ])),
    ]);

    $finder = app(MovieByImdbFinder::class);
    $result = $finder->find(new TorrentRequest(Kind::Movie, $movie, 15_000_000_000));

    expect($result['torrent_id'])->toBe(2);
});

it('returns null when only oversize matches exist', function () {
    $movie = Movie::factory()->create(['imdb_id' => 'tt1111111', 'title' => 'Test', 'year' => 2024]);

    Http::fake([
        'iptorrents.com/*' => Http::response(fakeIptSearchHtml([
            fakeIptTorrentRow(torrentId: 1, name: 'Test.2024.4K', size: '50 GB'),
        ])),
    ]);

    $finder = app(MovieByImdbFinder::class);
    expect($finder->find(new TorrentRequest(Kind::Movie, $movie, 15_000_000_000)))->toBeNull();
});

it('does not support movie without IMDB', function () {
    $movie = Movie::factory()->create(['imdb_id' => '', 'title' => 'Test', 'year' => 2024]);

    $finder = app(MovieByImdbFinder::class);
    expect($finder->supports(new TorrentRequest(Kind::Movie, $movie, 15_000_000_000)))->toBeFalse();
});
