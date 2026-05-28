<?php

use App\Enums\Language;
use App\Models\Movie;
use App\Services\Torrent\Finders\MovieByForeignTitleFinder;
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

it('does not support english movies', function () {
    $movie = Movie::factory()->create([
        'imdb_id' => 'tt3333333',
        'title' => 'English Movie',
        'original_language' => Language::English->value,
    ]);

    $finder = app(MovieByForeignTitleFinder::class);
    expect($finder->supports(new TorrentRequest(Kind::Movie, $movie, 15_000_000_000)))->toBeFalse();
});

it('queries original_title when foreign-language', function () {
    $movie = Movie::factory()->create([
        'imdb_id' => 'tt3333333',
        'title' => 'Squid Game',
        'year' => 2021,
        'original_language' => Language::Korean->value,
        'original_title' => 'Ojingeo Geim',
    ]);

    Http::fake(function ($req) {
        if (str_contains($req->url(), '/torrent.php')) {
            return Http::response(fakeIptTorrentDetailPage('tt3333333'));
        }

        return Http::response(fakeIptSearchHtml([
            fakeIptTorrentRow(torrentId: 1, name: 'Ojingeo.Geim.2021.1080p', size: '5 GB'),
        ]));
    });

    $finder = app(MovieByForeignTitleFinder::class);
    $result = $finder->find(new TorrentRequest(Kind::Movie, $movie, 15_000_000_000));

    expect($result['torrent_id'])->toBe(1);
    Http::assertSent(fn ($r) => ! str_contains($r->url(), '/torrent.php')
        && str_contains($r->url(), 'q=Ojingeo+Geim+2021')
        && str_contains($r->url(), '38='));
});

it('returns null when original_title matches title', function () {
    $movie = Movie::factory()->create([
        'imdb_id' => 'tt3333333',
        'title' => 'Same',
        'original_language' => Language::Korean->value,
        'original_title' => 'Same',
    ]);

    $finder = app(MovieByForeignTitleFinder::class);
    expect($finder->find(new TorrentRequest(Kind::Movie, $movie, 15_000_000_000)))->toBeNull();
    Http::assertNothingSent();
});

it('returns null when original_title is empty', function () {
    $movie = Movie::factory()->create([
        'imdb_id' => 'tt3333333',
        'title' => 'Movie',
        'original_language' => Language::Korean->value,
        'original_title' => null,
    ]);

    $finder = app(MovieByForeignTitleFinder::class);
    expect($finder->find(new TorrentRequest(Kind::Movie, $movie, 15_000_000_000)))->toBeNull();
    Http::assertNothingSent();
});
