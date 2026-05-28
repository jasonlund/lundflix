<?php

use App\Models\Show;
use App\Services\Torrent\Finders\SeasonPackByImdbFinder;
use App\Services\Torrent\Finders\SeasonPackByNameFinder;
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

it('SeasonPackByImdbFinder hits TV pack categories with season token', function () {
    $show = Show::factory()->create(['imdb_id' => 'tt6666666', 'name' => 'Show']);

    Http::fake([
        'iptorrents.com/*' => Http::response(fakeIptSearchHtml([
            fakeIptTorrentRow(torrentId: 1, name: 'Show.S03.1080p', size: '20 GB'),
        ])),
    ]);

    $finder = app(SeasonPackByImdbFinder::class);
    $result = $finder->find(new TorrentRequest(Kind::SeasonPack, ['show' => $show, 'season' => 3], 60_000_000_000));

    expect($result['torrent_id'])->toBe(1);
    Http::assertSent(fn ($r) => str_contains($r->url(), 'q=tt6666666+S03')
        && str_contains($r->url(), '65=')
        && str_contains($r->url(), '83='));
});

it('SeasonPackByImdbFinder skips oversize pack', function () {
    $show = Show::factory()->create(['imdb_id' => 'tt6666666', 'name' => 'Show']);

    Http::fake([
        'iptorrents.com/*' => Http::response(fakeIptSearchHtml([
            fakeIptTorrentRow(torrentId: 1, name: 'Show.S03.4K', size: '100 GB'),
        ])),
    ]);

    $finder = app(SeasonPackByImdbFinder::class);
    expect($finder->find(new TorrentRequest(Kind::SeasonPack, ['show' => $show, 'season' => 3], 60_000_000_000)))->toBeNull();
});

it('SeasonPackByNameFinder IMDB-verifies result', function () {
    $show = Show::factory()->create(['imdb_id' => 'tt6666666', 'name' => 'My Show']);

    Http::fake(function ($req) {
        if (str_contains($req->url(), '/torrent.php')) {
            return Http::response(fakeIptTorrentDetailPage('tt6666666'));
        }

        return Http::response(fakeIptSearchHtml([
            fakeIptTorrentRow(torrentId: 50, name: 'My.Show.S03.1080p', size: '15 GB'),
        ]));
    });

    $finder = app(SeasonPackByNameFinder::class);
    $result = $finder->find(new TorrentRequest(Kind::SeasonPack, ['show' => $show, 'season' => 3], 60_000_000_000));

    expect($result['torrent_id'])->toBe(50);
    Http::assertSent(fn ($r) => str_contains($r->url(), 'q=My+Show+s03')
        || str_contains($r->url(), '/torrent.php'));
});
