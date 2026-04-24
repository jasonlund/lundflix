<?php

use App\Enums\IptCategory;
use App\Exceptions\IptorrentsAuthException;
use App\Exceptions\IptorrentsRateLimitExceededException;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\Show;
use App\Services\IptorrentsService;
use App\Settings\IptorrentsSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Http::preventStrayRequests();
    RateLimiter::clear('iptorrents');

    $settings = app(IptorrentsSettings::class);
    $settings->ipt_uid = '123';
    $settings->ipt_pass = 'abc';
    $settings->save();
});

function fakeIptTorrentRow(
    int $torrentId = 12345,
    string $name = 'Test.Torrent.2024.1080p.WEB-DL.x264-GROUP',
    string $size = '4.2 GB',
    int $seeders = 50,
    int $leechers = 5,
    int $snatches = 200,
    string $uploaded = '35.0 seconds ago by Uploader',
    int $category = 20,
): string {
    $filename = str_replace(' ', '.', $name);

    return <<<HTML
        <tr>
            <td><a href="?{$category}"><img src="/cat.png" alt="Cat"></a></td>
            <td><a class="hv" href="/t/{$torrentId}">{$name}</a> <span class="tag">New</span><div class="sub">{$uploaded}</div></td>
            <td><a href="/t/{$torrentId}?bookmark" class="tTipWrap"><i class="fa fa-star fa-2x"></i></a></td>
            <td><a href="/download.php/{$torrentId}/{$filename}.torrent" class="tTipWrap"><i class="fa fa-download fa-2x grn"></i></a></td>
            <td><a href="/t/{$torrentId}?page=0#startcomments" class="tTipWrap">0</a></td>
            <td>{$size}</td>
            <td>{$seeders}</td>
            <td>{$leechers}</td>
            <td>{$snatches}</td>
        </tr>
    HTML;
}

function fakeIptSearchHtml(array $rows = []): string
{
    $rowsHtml = implode("\n", $rows);

    return <<<HTML
        <html>
        <head><title>Torrents - IPTorrents - #1 Private Tracker</title></head>
        <body>
        <table id="torrents">
            <thead><tr><th>Cat</th><th>Name</th><th>BM</th><th>DL</th><th>Cmt</th><th>Size</th><th>S</th><th>L</th><th>Sn</th></tr></thead>
            <tbody>
            {$rowsHtml}
            </tbody>
        </table>
        </body>
        </html>
    HTML;
}

function fakeIptLoginPage(): string
{
    return <<<'HTML'
        <html>
        <head><title>IPTorrents :: Login</title></head>
        <body><form action="/take_login.php"><input name="username" /><input name="password" /></form></body>
        </html>
    HTML;
}

it('parses search results from HTML response', function () {
    Http::fake([
        'iptorrents.com/*' => Http::response(fakeIptSearchHtml([
            fakeIptTorrentRow(torrentId: 111, name: 'Movie.2024.1080p.BluRay.x264-GRP', size: '8.1 GB', seeders: 120, leechers: 10, snatches: 500, uploaded: '2 hours ago by User1'),
            fakeIptTorrentRow(torrentId: 222, name: 'Show.S01E01.720p.WEB-DL-GRP', size: '1.3 GB', seeders: 45, leechers: 3, snatches: 80, uploaded: '5 minutes ago by User2'),
        ])),
    ]);

    $service = new IptorrentsService;
    $results = $service->search('test query');

    expect($results)->toHaveCount(2);

    expect($results->first())->toMatchArray([
        'torrent_id' => 111,
        'name' => 'Movie.2024.1080p.BluRay.x264-GRP',
        'size' => '8.1 GB',
        'seeders' => 120,
        'leechers' => 10,
        'snatches' => 500,
        'uploaded' => '2 hours ago by User1',
    ]);
    expect($results->first()['download_url'])->toContain('/download.php/111/');

    expect($results->last()['torrent_id'])->toBe(222);
});

it('sends cookie header with requests', function () {
    Http::fake([
        'iptorrents.com/*' => Http::response(fakeIptSearchHtml([])),
    ]);

    $service = new IptorrentsService;
    $service->search('test');

    Http::assertSent(fn ($request) => $request->hasHeader('Cookie', 'uid=123; pass=abc'));
});

it('builds correct search URL with categories', function () {
    Http::fake([
        'iptorrents.com/*' => Http::response(fakeIptSearchHtml([])),
    ]);

    $service = new IptorrentsService;
    $service->search('test query', [IptCategory::MovieWebDl, IptCategory::MovieX265]);

    Http::assertSent(function ($request) {
        $url = $request->url();

        return str_contains($url, '20=')
            && str_contains($url, '100=')
            && str_contains($url, 'q=test+query')
            && str_contains($url, 'o=seeders');
    });
});

it('handles sort parameter in URL', function () {
    Http::fake([
        'iptorrents.com/*' => Http::response(fakeIptSearchHtml([])),
    ]);

    $service = new IptorrentsService;
    $service->search('test', sort: 'size');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'o=size'));
});

it('throws IptorrentsAuthException when cookie is expired', function () {
    Http::fake([
        'iptorrents.com/*' => Http::response(fakeIptLoginPage()),
    ]);

    $service = new IptorrentsService;
    expect(fn () => $service->search('test'))->toThrow(IptorrentsAuthException::class);
});

it('throws IptorrentsAuthException when credentials are not configured', function () {
    $settings = app(IptorrentsSettings::class);
    $settings->ipt_uid = '';
    $settings->ipt_pass = '';
    $settings->save();

    $service = new IptorrentsService;
    expect(fn () => $service->search('test'))->toThrow(IptorrentsAuthException::class);

    Http::assertNothingSent();
});

it('throws IptorrentsRateLimitExceededException when rate limit exceeded', function () {
    foreach (range(1, 10) as $_) {
        RateLimiter::hit('iptorrents', 60);
    }

    Http::fake(['iptorrents.com/*' => Http::response(fakeIptSearchHtml([]))]);

    $service = new IptorrentsService;
    expect(fn () => $service->search('test'))
        ->toThrow(IptorrentsRateLimitExceededException::class);

    Http::assertNothingSent();
});

it('returns empty collection when no results found', function () {
    Http::fake([
        'iptorrents.com/*' => Http::response(fakeIptSearchHtml([])),
    ]);

    $service = new IptorrentsService;
    $results = $service->search('nonexistent query');

    expect($results)->toBeEmpty();
});

it('downloads torrent file and stores it', function () {
    Storage::fake('local');

    $torrentContent = 'd8:announce35:http://tracker.example.com/announcee';

    Http::fake([
        'iptorrents.com/*' => Http::response($torrentContent),
    ]);

    $service = new IptorrentsService;
    $path = $service->download(12345, 'Test.Torrent.torrent');

    expect($path)->toContain('private/torrents/Test.Torrent.torrent');

    Storage::disk('local')->assertExists('private/torrents/Test.Torrent.torrent');
    expect(Storage::disk('local')->get('private/torrents/Test.Torrent.torrent'))->toBe($torrentContent);
});

it('detects auth failure on download', function () {
    Http::fake([
        'iptorrents.com/*' => Http::response('<html><head><title>IPTorrents :: Login</title></head></html>'),
    ]);

    $service = new IptorrentsService;
    expect(fn () => $service->download(12345, 'test.torrent'))
        ->toThrow(IptorrentsAuthException::class);
});

it('skips malformed rows without breaking', function () {
    $malformedRow = '<tr><td>broken</td></tr>';

    Http::fake([
        'iptorrents.com/*' => Http::response(fakeIptSearchHtml([
            $malformedRow,
            fakeIptTorrentRow(torrentId: 999, name: 'Good.Torrent'),
        ])),
    ]);

    $service = new IptorrentsService;
    $results = $service->search('test');

    expect($results)->toHaveCount(1);
    expect($results->first()['torrent_id'])->toBe(999);
});

describe('searchMovie', function () {
    it('returns top seeded result from IMDB ID with default categories', function () {
        $movie = Movie::factory()->create(['imdb_id' => 'tt1234567', 'title' => 'Test Movie', 'year' => 2024]);

        Http::fake([
            'iptorrents.com/*' => Http::response(fakeIptSearchHtml([
                fakeIptTorrentRow(torrentId: 500, name: 'Test.Movie.2024.1080p.x265', seeders: 100),
            ])),
        ]);

        $service = new IptorrentsService;
        $result = $service->searchMovie($movie);

        expect($result)
            ->not->toBeNull()
            ->and($result['torrent_id'])->toBe(500);

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'q=tt1234567')
            && str_contains($request->url(), '100='));
    });

    it('falls back to IMDB ID with all movie categories', function () {
        $movie = Movie::factory()->create(['imdb_id' => 'tt1234567', 'title' => 'Test Movie', 'year' => 2024]);

        Http::fake([
            'iptorrents.com/*' => Http::sequence()
                ->push(fakeIptSearchHtml([]))
                ->push(fakeIptSearchHtml([
                    fakeIptTorrentRow(torrentId: 600, name: 'Test.Movie.2024.720p.BDRip', seeders: 50),
                ])),
        ]);

        $service = new IptorrentsService;
        $result = $service->searchMovie($movie);

        expect($result)
            ->not->toBeNull()
            ->and($result['torrent_id'])->toBe(600);

        Http::assertSentCount(2);
    });

    it('falls back to title and year with default categories', function () {
        $movie = Movie::factory()->create(['imdb_id' => 'tt1234567', 'title' => 'Test Movie', 'year' => 2024]);

        Http::fake([
            'iptorrents.com/*' => Http::sequence()
                ->push(fakeIptSearchHtml([]))
                ->push(fakeIptSearchHtml([]))
                ->push(fakeIptSearchHtml([
                    fakeIptTorrentRow(torrentId: 700, name: 'Test.Movie.2024.1080p.x265', seeders: 30),
                ])),
        ]);

        $service = new IptorrentsService;
        $result = $service->searchMovie($movie);

        expect($result)
            ->not->toBeNull()
            ->and($result['torrent_id'])->toBe(700);

        Http::assertSentCount(3);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'q=Test+Movie+2024'));
    });

    it('falls back to title and year with all movie categories', function () {
        $movie = Movie::factory()->create(['imdb_id' => 'tt1234567', 'title' => 'Test Movie', 'year' => 2024]);

        Http::fake([
            'iptorrents.com/*' => Http::sequence()
                ->push(fakeIptSearchHtml([]))
                ->push(fakeIptSearchHtml([]))
                ->push(fakeIptSearchHtml([]))
                ->push(fakeIptSearchHtml([
                    fakeIptTorrentRow(torrentId: 800, name: 'Test.Movie.2024.DVDRip', seeders: 10),
                ])),
        ]);

        $service = new IptorrentsService;
        $result = $service->searchMovie($movie);

        expect($result)
            ->not->toBeNull()
            ->and($result['torrent_id'])->toBe(800);

        Http::assertSentCount(4);
    });

    it('skips IMDB steps when movie has no IMDB ID', function () {
        $movie = Movie::factory()->create(['imdb_id' => '', 'title' => 'No IMDB Movie', 'year' => 2024]);

        Http::fake([
            'iptorrents.com/*' => Http::response(fakeIptSearchHtml([
                fakeIptTorrentRow(torrentId: 900, name: 'No.IMDB.Movie.2024.x265', seeders: 25),
            ])),
        ]);

        $service = new IptorrentsService;
        $result = $service->searchMovie($movie);

        expect($result)
            ->not->toBeNull()
            ->and($result['torrent_id'])->toBe(900);

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'q=No+IMDB+Movie+2024'));
    });

    it('returns null when nothing found', function () {
        $movie = Movie::factory()->create(['imdb_id' => 'tt9999999', 'title' => 'Unfindable Movie', 'year' => 2024]);

        Http::fake([
            'iptorrents.com/*' => Http::response(fakeIptSearchHtml([])),
        ]);

        $service = new IptorrentsService;
        $result = $service->searchMovie($movie);

        expect($result)->toBeNull();
        Http::assertSentCount(4);
    });
});

describe('searchEpisode', function () {
    it('returns result from IMDB ID + episode code with default categories', function () {
        $show = Show::factory()->create(['imdb_id' => 'tt7654321', 'name' => 'Test Show']);
        $episode = Episode::factory()->for($show)->create(['season' => 1, 'number' => 5]);

        Http::fake([
            'iptorrents.com/*' => Http::response(fakeIptSearchHtml([
                fakeIptTorrentRow(torrentId: 501, name: 'Test.Show.S01E05.1080p.x265', seeders: 80),
            ])),
        ]);

        $service = new IptorrentsService;
        $result = $service->searchEpisode($episode);

        expect($result)
            ->not->toBeNull()
            ->and($result['torrent_id'])->toBe(501);

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'q=tt7654321+s01e05')
            && str_contains($request->url(), '5=')
            && str_contains($request->url(), '99='));
    });

    it('falls back to IMDB + all TV categories', function () {
        $show = Show::factory()->create(['imdb_id' => 'tt7654321', 'name' => 'Test Show']);
        $episode = Episode::factory()->for($show)->create(['season' => 2, 'number' => 3]);

        Http::fake([
            'iptorrents.com/*' => Http::sequence()
                ->push(fakeIptSearchHtml([]))
                ->push(fakeIptSearchHtml([
                    fakeIptTorrentRow(torrentId: 502, name: 'Test.Show.S02E03.720p', seeders: 40),
                ])),
        ]);

        $service = new IptorrentsService;
        $result = $service->searchEpisode($episode);

        expect($result)
            ->not->toBeNull()
            ->and($result['torrent_id'])->toBe(502);

        Http::assertSentCount(2);
    });

    it('falls back to name + episode code with default categories', function () {
        $show = Show::factory()->create(['imdb_id' => 'tt7654321', 'name' => 'Test Show']);
        $episode = Episode::factory()->for($show)->create(['season' => 1, 'number' => 1]);

        Http::fake([
            'iptorrents.com/*' => Http::sequence()
                ->push(fakeIptSearchHtml([]))
                ->push(fakeIptSearchHtml([]))
                ->push(fakeIptSearchHtml([
                    fakeIptTorrentRow(torrentId: 503, name: 'Test.Show.S01E01.1080p', seeders: 30),
                ])),
        ]);

        $service = new IptorrentsService;
        $result = $service->searchEpisode($episode);

        expect($result)
            ->not->toBeNull()
            ->and($result['torrent_id'])->toBe(503);

        Http::assertSentCount(3);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'q=Test+Show+s01e01'));
    });

    it('falls back to name + episode code with all TV categories', function () {
        $show = Show::factory()->create(['imdb_id' => 'tt7654321', 'name' => 'Test Show']);
        $episode = Episode::factory()->for($show)->create(['season' => 3, 'number' => 10]);

        Http::fake([
            'iptorrents.com/*' => Http::sequence()
                ->push(fakeIptSearchHtml([]))
                ->push(fakeIptSearchHtml([]))
                ->push(fakeIptSearchHtml([]))
                ->push(fakeIptSearchHtml([
                    fakeIptTorrentRow(torrentId: 504, name: 'Test.Show.S03E10.DVDRip', seeders: 10),
                ])),
        ]);

        $service = new IptorrentsService;
        $result = $service->searchEpisode($episode);

        expect($result)
            ->not->toBeNull()
            ->and($result['torrent_id'])->toBe(504);

        Http::assertSentCount(4);
    });

    it('skips IMDB steps when show has no IMDB ID', function () {
        $show = Show::factory()->create(['imdb_id' => '', 'name' => 'No IMDB Show']);
        $episode = Episode::factory()->for($show)->create(['season' => 1, 'number' => 2]);

        Http::fake([
            'iptorrents.com/*' => Http::response(fakeIptSearchHtml([
                fakeIptTorrentRow(torrentId: 505, name: 'No.IMDB.Show.S01E02', seeders: 20),
            ])),
        ]);

        $service = new IptorrentsService;
        $result = $service->searchEpisode($episode);

        expect($result)
            ->not->toBeNull()
            ->and($result['torrent_id'])->toBe(505);

        Http::assertSentCount(1);
    });

    it('returns null when nothing found', function () {
        $show = Show::factory()->create(['imdb_id' => 'tt9999999', 'name' => 'Unfindable Show']);
        $episode = Episode::factory()->for($show)->create(['season' => 1, 'number' => 1]);

        Http::fake([
            'iptorrents.com/*' => Http::response(fakeIptSearchHtml([])),
        ]);

        $service = new IptorrentsService;
        $result = $service->searchEpisode($episode);

        expect($result)->toBeNull();
        Http::assertSentCount(4);
    });
});
