<?php

use App\Models\Episode;
use App\Models\Show;
use App\Services\Torrent\Finders\EpisodeByImdbFinder;
use App\Services\Torrent\Finders\EpisodeByNameFinder;
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

describe('EpisodeByImdbFinder', function () {
    it('finds episode with IMDB query and skips oversize', function () {
        $show = Show::factory()->create(['imdb_id' => 'tt4444444', 'name' => 'Show']);
        $episode = Episode::factory()->for($show)->create(['season' => 1, 'number' => 5]);

        Http::fake([
            'iptorrents.com/*' => Http::response(fakeIptSearchHtml([
                fakeIptTorrentRow(torrentId: 1, name: 'Show.S01E05.4K', size: '10 GB', seeders: 200),
                fakeIptTorrentRow(torrentId: 2, name: 'Show.S01E05.1080p', size: '2 GB', seeders: 100),
            ])),
        ]);

        $finder = app(EpisodeByImdbFinder::class);
        $result = $finder->find(new TorrentRequest(Kind::Episode, $episode, 4_000_000_000));

        expect($result['torrent_id'])->toBe(2);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'q=tt4444444+s01e05'));
    });

    it('returns null when only oversize matches', function () {
        $show = Show::factory()->create(['imdb_id' => 'tt4444444', 'name' => 'Show']);
        $episode = Episode::factory()->for($show)->create(['season' => 1, 'number' => 1]);

        Http::fake([
            'iptorrents.com/*' => Http::response(fakeIptSearchHtml([
                fakeIptTorrentRow(torrentId: 1, name: 'Show.S01E01.4K', size: '10 GB'),
            ])),
        ]);

        $finder = app(EpisodeByImdbFinder::class);
        expect($finder->find(new TorrentRequest(Kind::Episode, $episode, 4_000_000_000)))->toBeNull();
    });
});

describe('EpisodeByNameFinder', function () {
    it('finds IMDB-verified episode by show name', function () {
        $show = Show::factory()->create(['imdb_id' => 'tt5555555', 'name' => 'My Show']);
        $episode = Episode::factory()->for($show)->create(['season' => 2, 'number' => 3]);

        Http::fake(function ($req) {
            if (str_contains($req->url(), '/torrent.php')) {
                return Http::response(fakeIptTorrentDetailPage('tt5555555'));
            }

            return Http::response(fakeIptSearchHtml([
                fakeIptTorrentRow(torrentId: 10, name: 'My.Show.S02E03.1080p', size: '2 GB'),
            ]));
        });

        $finder = app(EpisodeByNameFinder::class);
        expect($finder->find(new TorrentRequest(Kind::Episode, $episode, 4_000_000_000))['torrent_id'])->toBe(10);
    });

    it('skips oversize and picks fitting next', function () {
        $show = Show::factory()->create(['imdb_id' => 'tt5555555', 'name' => 'My Show']);
        $episode = Episode::factory()->for($show)->create(['season' => 1, 'number' => 1]);

        Http::fake(function ($req) {
            if (str_contains($req->url(), '/torrent.php')) {
                return Http::response(fakeIptTorrentDetailPage('tt5555555'));
            }

            return Http::response(fakeIptSearchHtml([
                fakeIptTorrentRow(torrentId: 10, name: 'My.Show.S01E01.4K', size: '20 GB', seeders: 200),
                fakeIptTorrentRow(torrentId: 11, name: 'Other.Show.S01E01.1080p', size: '2 GB', seeders: 100),
            ]));
        });

        $finder = app(EpisodeByNameFinder::class);
        expect($finder->find(new TorrentRequest(Kind::Episode, $episode, 4_000_000_000))['torrent_id'])->toBe(11);
    });
});
