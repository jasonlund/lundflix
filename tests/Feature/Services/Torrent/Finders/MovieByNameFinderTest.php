<?php

use App\Models\Movie;
use App\Services\Torrent\Finders\MovieByNameFinder;
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

it('finds verified match within size cap', function () {
    $movie = Movie::factory()->create(['imdb_id' => 'tt2222222', 'title' => 'Foo Bar', 'year' => 2024]);

    Http::fake(function ($req) {
        if (str_contains($req->url(), '/torrent.php')) {
            return Http::response(fakeIptTorrentDetailPage('tt2222222'));
        }

        return Http::response(fakeIptSearchHtml([
            fakeIptTorrentRow(torrentId: 1, name: 'Foo.Bar.2024.1080p', size: '8 GB'),
        ]));
    });

    $finder = app(MovieByNameFinder::class);
    expect($finder->find(new TorrentRequest(Kind::Movie, $movie, 15_000_000_000))['torrent_id'])->toBe(1);
});

it('skips oversize candidates and picks smaller fitting one', function () {
    $movie = Movie::factory()->create(['imdb_id' => 'tt2222222', 'title' => 'Foo Bar', 'year' => 2024]);

    Http::fake(function ($req) {
        if (str_contains($req->url(), '/torrent.php')) {
            return Http::response(fakeIptTorrentDetailPage('tt2222222'));
        }

        return Http::response(fakeIptSearchHtml([
            fakeIptTorrentRow(torrentId: 1, name: 'Foo.Bar.2024.4K', size: '50 GB', seeders: 200),
            fakeIptTorrentRow(torrentId: 2, name: 'Foo.Bar.2024.1080p', size: '8 GB', seeders: 100),
        ]));
    });

    $finder = app(MovieByNameFinder::class);
    expect($finder->find(new TorrentRequest(Kind::Movie, $movie, 15_000_000_000))['torrent_id'])->toBe(2);
});

it('returns null when only oversize matches exist', function () {
    $movie = Movie::factory()->create(['imdb_id' => 'tt2222222', 'title' => 'Foo Bar', 'year' => 2024]);

    Http::fake([
        'iptorrents.com/*' => Http::response(fakeIptSearchHtml([
            fakeIptTorrentRow(torrentId: 1, name: 'Foo.Bar.2024.4K', size: '50 GB'),
        ])),
    ]);

    $finder = app(MovieByNameFinder::class);
    expect($finder->find(new TorrentRequest(Kind::Movie, $movie, 15_000_000_000)))->toBeNull();
});
