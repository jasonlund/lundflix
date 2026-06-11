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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

beforeEach(function () {
    Http::preventStrayRequests();
    resetIptThrottle();

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

function fakeIptTorrentDetailPage(string $imdbId = 'tt7654321'): string
{
    return <<<HTML
        <html>
        <head><title>Test Torrent - IPTorrents - #1 Private Tracker</title></head>
        <body>
        <table><tr><td style="display:flex;gap:8px;">
            <a href="https://www.themoviedb.org/tv/12345/" target="_blank">TMDB</a>
            <a href="https://www.imdb.com/title/{$imdbId}/" target="_blank">IMDb</a>
        </td></tr></table>
        </body>
        </html>
    HTML;
}

/**
 * @param  list<string|array{0: string, 1: string}>  $files
 */
function fakeIptFileListHtml(array $files = ['Some.Movie.2024.1080p.BluRay.x264-GRP.mkv']): string
{
    $rows = '';

    foreach ($files as $file) {
        $path = is_array($file) ? $file[0] : $file;
        $size = is_array($file) ? ($file[1] ?? '100 MB') : '100 MB';
        $rows .= "<tr><td>{$path}<td class=ar>{$size}";
    }

    return <<<HTML
        <html>
        <head><title>IPTorrents - #1 Private Tracker</title></head>
        <body>
        <table id=body><tr><td>chrome</table>
        <table class=t1><tr><th>Name<th class=ar>Size{$rows}</table>
        </body>
        </html>
    HTML;
}

function fakeIptTorrentDetailPageWithoutImdb(): string
{
    return <<<'HTML'
        <html>
        <head><title>Test Torrent - IPTorrents - #1 Private Tracker</title></head>
        <body>
        <table><tr><td style="display:flex;gap:8px;">
            <a href="https://www.themoviedb.org/tv/12345/" target="_blank">TMDB</a>
        </td></tr></table>
        </body>
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
    config(['services.iptorrents.uid' => '', 'services.iptorrents.pass' => '']);

    $settings = app(IptorrentsSettings::class);
    $settings->ipt_uid = '';
    $settings->ipt_pass = '';
    $settings->save();

    $service = new IptorrentsService;
    expect(fn () => $service->search('test'))->toThrow(IptorrentsAuthException::class);

    Http::assertNothingSent();
});

it('throws IptorrentsRateLimitExceededException when in cooldown', function () {
    seedIptCooldown();

    Http::fake(['iptorrents.com/*' => Http::response(fakeIptSearchHtml([]))]);

    $service = new IptorrentsService;
    expect(fn () => $service->search('test'))
        ->toThrow(IptorrentsRateLimitExceededException::class);

    Http::assertNothingSent();
});

it('spaces consecutive requests by the configured interval', function () {
    $this->freezeTime();

    Http::fake(['iptorrents.com/*' => Http::response(fakeIptSearchHtml([]))]);

    $service = new IptorrentsService;
    $service->search('first');
    $service->search('second');

    Sleep::assertSlept(fn ($duration) => (int) $duration->totalMilliseconds === 6500, times: 1);
});

it('honors and logs Retry-After on a 429 without retrying', function () {
    Log::spy();

    Http::fake([
        'iptorrents.com/*' => Http::response('Rate Limit Reached', 429, ['Retry-After' => '60']),
    ]);

    $service = new IptorrentsService;

    try {
        $service->search('test');
        $this->fail('Expected IptorrentsRateLimitExceededException.');
    } catch (IptorrentsRateLimitExceededException $e) {
        expect($e->retryAfter)->toBe(60);
    }

    Http::assertSentCount(1);
    Log::shouldHaveReceived('warning')->once();
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

describe('H.265 preference', function () {
    it('prefers the H.265 release over a higher-seeded WEB-DL H.264 for episodes', function () {
        $show = Show::factory()->create(['imdb_id' => 'tt3530232', 'name' => 'Last Week Tonight with John Oliver']);
        $episode = Episode::factory()->for($show)->create(['season' => 13, 'number' => 14]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/torrent.php')) {
                return Http::response(fakeIptTorrentDetailPage('tt3530232'));
            }

            return Http::response(fakeIptSearchHtml([
                fakeIptTorrentRow(torrentId: 1, name: 'Last Week Tonight with John Oliver S13E14 1080p AMZN WEB-DL DDP2 0 H 264-NTb', seeders: 1605),
                fakeIptTorrentRow(torrentId: 2, name: 'Last Week Tonight with John Oliver S13E14 1080p HEVC x265-MeGusta', seeders: 705),
            ]));
        });

        $service = new IptorrentsService;
        $result = $service->searchEpisodeByName($episode);

        expect($result)
            ->not->toBeNull()
            ->and($result['torrent_id'])->toBe(2);
    });

    it('keeps seeder order when no H.265 release is present', function () {
        $show = Show::factory()->create(['imdb_id' => 'tt3530232', 'name' => 'Last Week Tonight with John Oliver']);
        $episode = Episode::factory()->for($show)->create(['season' => 13, 'number' => 14]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/torrent.php')) {
                return Http::response(fakeIptTorrentDetailPage('tt3530232'));
            }

            return Http::response(fakeIptSearchHtml([
                fakeIptTorrentRow(torrentId: 10, name: 'Last Week Tonight with John Oliver S13E14 1080p AMZN WEB-DL DDP2 0 H 264-NTb', seeders: 1605),
                fakeIptTorrentRow(torrentId: 11, name: 'Last Week Tonight with John Oliver S13E14 480p x264-mSD', seeders: 63),
            ]));
        });

        $service = new IptorrentsService;
        $result = $service->searchEpisodeByName($episode);

        expect($result)
            ->not->toBeNull()
            ->and($result['torrent_id'])->toBe(10);
    });

    it('does not treat AV1 as H.265', function () {
        $service = new IptorrentsService;
        $method = new ReflectionMethod($service, 'isH265');

        expect($method->invoke($service, 'Show S01E01 1080p AV1 10bit-MeGusta'))->toBeFalse()
            ->and($method->invoke($service, 'Show S01E01 1080p AMZN WEB-DL H 264-NTb'))->toBeFalse()
            ->and($method->invoke($service, 'Show S01E01 1080p HEVC x265-MeGusta'))->toBeTrue()
            ->and($method->invoke($service, 'Show S01E01 720p WEBRip 2CH x265 HEVC-PSA'))->toBeTrue()
            ->and($method->invoke($service, 'Show S01E01 2160p WEB-DL H 265-SCOPE'))->toBeTrue()
            ->and($method->invoke($service, 'Show S01E01 1080p Xbox265 H 264-NTb'))->toBeFalse()
            ->and($method->invoke($service, 'Show S01E01 1080p H 264 Phx265 release'))->toBeFalse();
    });
});

describe('RAR detection', function () {
    it('treats multipart .rNN volumes as rar-packed', function () {
        $service = new IptorrentsService;
        $method = new ReflectionMethod($service, 'isRarPacked');

        expect($method->invoke($service, [
            'blade.runner.2049.1080p.bluray.x264-sparks.nfo',
            'blade.runner.2049.1080p.bluray.x264-sparks.r00',
            'blade.runner.2049.1080p.bluray.x264-sparks.r01',
        ]))->toBeTrue();
    });

    it('treats multipart volumes beyond .r99 as rar-packed', function () {
        $service = new IptorrentsService;
        $method = new ReflectionMethod($service, 'isRarPacked');

        expect($method->invoke($service, [
            'huge.multidisc.rip.1080p.bluray.x264-grp.r099',
            'huge.multidisc.rip.1080p.bluray.x264-grp.r100',
            'huge.multidisc.rip.1080p.bluray.x264-grp.r123',
        ]))->toBeTrue();
    });

    it('treats a lone .rar as rar-packed', function () {
        $service = new IptorrentsService;
        $method = new ReflectionMethod($service, 'isRarPacked');

        expect($method->invoke($service, ['Some.Old.Rip.2004.DVDRip.XviD-GRP.rar']))->toBeTrue();
    });

    it('does not flag a direct mkv accompanied only by a subtitle rar', function () {
        $service = new IptorrentsService;
        $method = new ReflectionMethod($service, 'isRarPacked');

        expect($method->invoke($service, [
            'Subs/the.martian.2015.1080p.bluray.x264-sparks.subs.rar',
            'The.Martian.2015.1080p.BluRay.x264-SPARKS.mkv',
        ]))->toBeFalse();
    });

    it('does not flag a sample rar', function () {
        $service = new IptorrentsService;
        $method = new ReflectionMethod($service, 'isRarPacked');

        expect($method->invoke($service, [
            'Sample/movie-sample.rar',
            'Movie.2024.1080p.BluRay.x264-GRP.mkv',
        ]))->toBeFalse();
    });

    it('flags a root-level rar whose name merely starts with "sample"', function () {
        $service = new IptorrentsService;
        $method = new ReflectionMethod($service, 'isRarPacked');

        expect($method->invoke($service, [
            'Sample.Collection.2024.rar',
            'Sample.Collection.2024.mkv',
        ]))->toBeTrue();
    });

    it('does not flag a subtitle rar inside a "Subtitles" directory', function () {
        $service = new IptorrentsService;
        $method = new ReflectionMethod($service, 'isRarPacked');

        expect($method->invoke($service, [
            'Subtitles/the.martian.2015.1080p.bluray.x264-sparks.rar',
            'The.Martian.2015.1080p.BluRay.x264-SPARKS.mkv',
        ]))->toBeFalse();
    });

    it('does not flag a direct release whose folder name contains "NO RAR"', function () {
        $service = new IptorrentsService;
        $method = new ReflectionMethod($service, 'isRarPacked');

        expect($method->invoke($service, [
            'Alien Covenant 2017 1080p BluRay x264-SPARKS[NO RAR]/alien.covenant.mkv',
        ]))->toBeFalse();
    });

    it('matches NORAR title tag variants', function () {
        $service = new IptorrentsService;
        $method = new ReflectionMethod($service, 'hasNoRarTag');

        expect($method->invoke($service, 'Movie 2024 1080p x264-GRP[NORAR]'))->toBeTrue()
            ->and($method->invoke($service, 'Movie 2024 1080p x264-GRP[NoRAR]'))->toBeTrue()
            ->and($method->invoke($service, 'Top 500 PSP Games ISO-CSO NoRar IPT'))->toBeTrue()
            ->and($method->invoke($service, 'Alien Covenant 2017 1080p x264-SPARKS[NO RAR]'))->toBeTrue()
            ->and($method->invoke($service, '[NORAR]Pingu S01-S06 DVDRip XviD-aAF'))->toBeTrue()
            ->and($method->invoke($service, 'Blade Runner 2049 2017 1080p BluRay x264-SPARKS'))->toBeFalse();
    });

    it('parses the file list from a torrent files page', function () {
        Http::fake([
            'iptorrents.com/*' => Http::response(fakeIptFileListHtml([
                ['Movie.2024.1080p.BluRay.x264-GRP.mkv', '8 GB'],
                ['Movie.2024.1080p.BluRay.x264-GRP.nfo', '4 KB'],
            ])),
        ]);

        $service = new IptorrentsService;
        $files = $service->fetchTorrentFileList(12345);

        expect($files)->toBe([
            'Movie.2024.1080p.BluRay.x264-GRP.mkv',
            'Movie.2024.1080p.BluRay.x264-GRP.nfo',
        ]);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/t/12345/files'));
    });

    it('skips a rar-packed top result in favour of the next non-rar release', function () {
        $movie = Movie::factory()->create(['imdb_id' => 'tt1234567', 'title' => 'Test Movie', 'year' => 2024]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/t/700/files')) {
                return Http::response(fakeIptFileListHtml(['test.movie.2024.1080p.bluray.x264-grp.r00']));
            }

            if (str_contains($request->url(), '/t/701/files')) {
                return Http::response(fakeIptFileListHtml(['Test.Movie.2024.1080p.BluRay.x264-GRP.mkv']));
            }

            if (str_contains($request->url(), '/torrent.php')) {
                return Http::response(fakeIptTorrentDetailPage('tt1234567'));
            }

            return Http::response(fakeIptSearchHtml([
                fakeIptTorrentRow(torrentId: 700, name: 'Test.Movie.2024.1080p.BluRay.x264-GRP', seeders: 200),
                fakeIptTorrentRow(torrentId: 701, name: 'Test.Movie.2024.1080p.BluRay.x264-GRP2', seeders: 100),
            ]));
        });

        $service = new IptorrentsService;
        $result = $service->searchMovieByName($movie);

        expect($result)
            ->not->toBeNull()
            ->and($result['torrent_id'])->toBe(701);
    });

    it('falls back to a rar-packed match when no non-rar release is found', function () {
        $movie = Movie::factory()->create(['imdb_id' => 'tt1234567', 'title' => 'Test Movie', 'year' => 2024]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/files')) {
                return Http::response(fakeIptFileListHtml(['test.movie.2024.1080p.bluray.x264-grp.r00']));
            }

            if (str_contains($request->url(), '/torrent.php')) {
                return Http::response(fakeIptTorrentDetailPage('tt1234567'));
            }

            return Http::response(fakeIptSearchHtml([
                fakeIptTorrentRow(torrentId: 710, name: 'Test.Movie.2024.1080p.BluRay.x264-GRP', seeders: 200),
            ]));
        });

        $service = new IptorrentsService;
        $result = $service->searchMovieByName($movie);

        expect($result)
            ->not->toBeNull()
            ->and($result['torrent_id'])->toBe(710);
    });

    it('trusts a NORAR title tag without fetching the file list', function () {
        $movie = Movie::factory()->create(['imdb_id' => 'tt1234567', 'title' => 'Test Movie', 'year' => 2024]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/torrent.php')) {
                return Http::response(fakeIptTorrentDetailPage('tt1234567'));
            }

            return Http::response(fakeIptSearchHtml([
                fakeIptTorrentRow(torrentId: 720, name: 'Test.Movie.2024.1080p.BluRay.x264-GRP[NORAR]', seeders: 200),
            ]));
        });

        $service = new IptorrentsService;
        $result = $service->searchMovieByName($movie);

        expect($result)
            ->not->toBeNull()
            ->and($result['torrent_id'])->toBe(720);

        // 1 search + 1 IMDB lookup, no file-list check (title is trusted).
        Http::assertSentCount(2);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/files'));
    });
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

        // 1 search + 1 file-list check (untagged top result).
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'q=tt1234567')
            && str_contains($request->url(), '100='));
    });

    it('returns null when IMDB search finds nothing', function () {
        $movie = Movie::factory()->create(['imdb_id' => 'tt9999999', 'title' => 'Unfindable Movie', 'year' => 2024]);

        Http::fake([
            'iptorrents.com/*' => Http::response(fakeIptSearchHtml([])),
        ]);

        $service = new IptorrentsService;
        $result = $service->searchMovie($movie);

        expect($result)->toBeNull();
        Http::assertSentCount(1);
    });

    it('returns null when movie has no IMDB ID', function () {
        $movie = Movie::factory()->create(['imdb_id' => '', 'title' => 'No IMDB Movie', 'year' => 2024]);

        $service = new IptorrentsService;
        $result = $service->searchMovie($movie);

        expect($result)->toBeNull();
        Http::assertNothingSent();
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

        // 1 search + 1 file-list check (untagged top result).
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'q=tt7654321+s01e05')
            && str_contains($request->url(), '5=')
            && str_contains($request->url(), '99='));
    });

    it('returns null when show has no IMDB ID', function () {
        $show = Show::factory()->create(['imdb_id' => '', 'name' => 'No IMDB Show']);
        $episode = Episode::factory()->for($show)->create(['season' => 1, 'number' => 2]);

        Http::fake([
            'iptorrents.com/*' => Http::response(fakeIptSearchHtml([
                fakeIptTorrentRow(torrentId: 505, name: 'No.IMDB.Show.S01E02', seeders: 20),
            ])),
        ]);

        $service = new IptorrentsService;
        $result = $service->searchEpisode($episode);

        expect($result)->toBeNull();
        Http::assertNothingSent();
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
        Http::assertSentCount(1);
    });
});

describe('fetchTorrentImdbId', function () {
    it('extracts IMDB ID from torrent detail page', function () {
        Http::fake([
            'iptorrents.com/*' => Http::response(fakeIptTorrentDetailPage('tt7772588')),
        ]);

        $service = new IptorrentsService;
        $result = $service->fetchTorrentImdbId(12345);

        expect($result)->toBe('tt7772588');
    });

    it('returns null when detail page has no IMDB link', function () {
        Http::fake([
            'iptorrents.com/*' => Http::response(fakeIptTorrentDetailPageWithoutImdb()),
        ]);

        $service = new IptorrentsService;
        $result = $service->fetchTorrentImdbId(12345);

        expect($result)->toBeNull();
    });

    it('throws rate limit exception when in cooldown', function () {
        seedIptCooldown();

        $service = new IptorrentsService;
        expect(fn () => $service->fetchTorrentImdbId(12345))
            ->toThrow(IptorrentsRateLimitExceededException::class);

        Http::assertNothingSent();
    });

    it('throws auth exception on login page', function () {
        Http::fake([
            'iptorrents.com/*' => Http::response(fakeIptLoginPage()),
        ]);

        $service = new IptorrentsService;
        expect(fn () => $service->fetchTorrentImdbId(12345))
            ->toThrow(IptorrentsAuthException::class);
    });
});

describe('searchMovieByName', function () {
    it('returns verified result when name search finds match', function () {
        $movie = Movie::factory()->create(['imdb_id' => 'tt1234567', 'title' => 'Test Movie', 'year' => 2024]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/files')) {
                return Http::response(fakeIptFileListHtml());
            }

            if (str_contains($request->url(), '/torrent.php')) {
                return Http::response(fakeIptTorrentDetailPage('tt1234567'));
            }

            return Http::response(fakeIptSearchHtml([
                fakeIptTorrentRow(torrentId: 600, name: 'Test.Movie.2024.1080p.x265', seeders: 50),
            ]));
        });

        $service = new IptorrentsService;
        $result = $service->searchMovieByName($movie);

        expect($result)
            ->not->toBeNull()
            ->and($result['torrent_id'])->toBe(600);

        // 1 search + 1 IMDB lookup + 1 file-list check.
        Http::assertSentCount(3);
    });

    it('rejects result when IMDB does not match', function () {
        $movie = Movie::factory()->create(['imdb_id' => 'tt1234567', 'title' => 'Test Movie', 'year' => 2024]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/torrent.php')) {
                return Http::response(fakeIptTorrentDetailPage('tt9999999'));
            }

            return Http::response(fakeIptSearchHtml([
                fakeIptTorrentRow(torrentId: 601, name: 'Wrong.Movie.2024', seeders: 30),
            ]));
        });

        $service = new IptorrentsService;
        $result = $service->searchMovieByName($movie);

        expect($result)->toBeNull();
        Http::assertSentCount(2);
    });

    it('returns null when movie has no IMDB ID', function () {
        $movie = Movie::factory()->create(['imdb_id' => '', 'title' => 'No IMDB Movie', 'year' => 2024]);

        $service = new IptorrentsService;
        $result = $service->searchMovieByName($movie);

        expect($result)->toBeNull();
        Http::assertNothingSent();
    });

    it('sanitizes title in search URL', function () {
        $movie = Movie::factory()->create(['imdb_id' => 'tt1234567', 'title' => "Widow's Bay", 'year' => 2024]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/torrent.php')) {
                return Http::response(fakeIptTorrentDetailPage('tt1234567'));
            }

            return Http::response(fakeIptSearchHtml([
                fakeIptTorrentRow(torrentId: 603, name: 'Widows.Bay.2024.x265', seeders: 10),
            ]));
        });

        $service = new IptorrentsService;
        $service->searchMovieByName($movie);

        Http::assertSent(fn ($request) => ! str_contains($request->url(), '/torrent.php')
            && str_contains($request->url(), 'q=Widows+Bay+2024'));
    });

    it('replaces hyphens with spaces in search URL', function () {
        $movie = Movie::factory()->create(['imdb_id' => 'tt1234567', 'title' => 'Spider-Man', 'year' => 2024]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/torrent.php')) {
                return Http::response(fakeIptTorrentDetailPage('tt1234567'));
            }

            return Http::response(fakeIptSearchHtml([
                fakeIptTorrentRow(torrentId: 604, name: 'Spider.Man.2024.x265', seeders: 10),
            ]));
        });

        $service = new IptorrentsService;
        $service->searchMovieByName($movie);

        Http::assertSent(fn ($request) => ! str_contains($request->url(), '/torrent.php')
            && str_contains($request->url(), 'q=Spider+Man+2024'));
    });

    it('returns null when search finds nothing', function () {
        $movie = Movie::factory()->create(['imdb_id' => 'tt1234567', 'title' => 'Ghost Movie', 'year' => 2024]);

        Http::fake([
            'iptorrents.com/*' => Http::response(fakeIptSearchHtml([])),
        ]);

        $service = new IptorrentsService;
        $result = $service->searchMovieByName($movie);

        expect($result)->toBeNull();
        Http::assertSentCount(1);
    });

    it('finds match at second position when first IMDB does not match', function () {
        $movie = Movie::factory()->create(['imdb_id' => 'tt1234567', 'title' => 'Test Movie', 'year' => 2024]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/files')) {
                return Http::response(fakeIptFileListHtml());
            }

            if (str_contains($request->url(), '/torrent.php?id=800')) {
                return Http::response(fakeIptTorrentDetailPage('tt9999999'));
            }

            if (str_contains($request->url(), '/torrent.php?id=801')) {
                return Http::response(fakeIptTorrentDetailPage('tt1234567'));
            }

            return Http::response(fakeIptSearchHtml([
                fakeIptTorrentRow(torrentId: 800, name: 'Wrong.Movie.2024.1080p', seeders: 100),
                fakeIptTorrentRow(torrentId: 801, name: 'Test.Movie.2024.1080p', seeders: 50),
            ]));
        });

        $service = new IptorrentsService;
        $result = $service->searchMovieByName($movie);

        expect($result)
            ->not->toBeNull()
            ->and($result['torrent_id'])->toBe(801);

        // 1 search + 2 IMDB lookups + 1 file-list check (id801).
        Http::assertSentCount(4);
    });

    it('caps IMDB lookups at maximum', function () {
        $movie = Movie::factory()->create(['imdb_id' => 'tt1234567', 'title' => 'Test Movie', 'year' => 2024]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/torrent.php')) {
                return Http::response(fakeIptTorrentDetailPage('tt9999999'));
            }

            return Http::response(fakeIptSearchHtml([
                fakeIptTorrentRow(torrentId: 810, name: 'Wrong.1.2024', seeders: 100),
                fakeIptTorrentRow(torrentId: 811, name: 'Wrong.2.2024', seeders: 90),
                fakeIptTorrentRow(torrentId: 812, name: 'Wrong.3.2024', seeders: 80),
                fakeIptTorrentRow(torrentId: 813, name: 'Wrong.4.2024', seeders: 70),
                fakeIptTorrentRow(torrentId: 814, name: 'Wrong.5.2024', seeders: 60),
            ]));
        });

        $service = new IptorrentsService;
        $result = $service->searchMovieByName($movie);

        expect($result)->toBeNull();

        // 1 search + 3 IMDB lookups (capped, not all 5)
        Http::assertSentCount(4);
    });
});

describe('searchEpisodeByName', function () {
    it('returns verified result when name search finds match', function () {
        $show = Show::factory()->create(['imdb_id' => 'tt7654321', 'name' => 'Test Show']);
        $episode = Episode::factory()->for($show)->create(['season' => 1, 'number' => 5]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/files')) {
                return Http::response(fakeIptFileListHtml());
            }

            if (str_contains($request->url(), '/torrent.php')) {
                return Http::response(fakeIptTorrentDetailPage('tt7654321'));
            }

            return Http::response(fakeIptSearchHtml([
                fakeIptTorrentRow(torrentId: 700, name: 'Test.Show.S01E05.1080p.x265', seeders: 80),
            ]));
        });

        $service = new IptorrentsService;
        $result = $service->searchEpisodeByName($episode);

        expect($result)
            ->not->toBeNull()
            ->and($result['torrent_id'])->toBe(700);

        // 1 search + 1 IMDB lookup + 1 file-list check.
        Http::assertSentCount(3);
    });

    it('rejects result when IMDB does not match', function () {
        $show = Show::factory()->create(['imdb_id' => 'tt7654321', 'name' => 'Test Show']);
        $episode = Episode::factory()->for($show)->create(['season' => 2, 'number' => 3]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/torrent.php')) {
                return Http::response(fakeIptTorrentDetailPage('tt9999999'));
            }

            return Http::response(fakeIptSearchHtml([
                fakeIptTorrentRow(torrentId: 701, name: 'Wrong.Show.S02E03', seeders: 40),
            ]));
        });

        $service = new IptorrentsService;
        $result = $service->searchEpisodeByName($episode);

        expect($result)->toBeNull();
        Http::assertSentCount(2);
    });

    it('rejects result when detail page has no IMDB link', function () {
        $show = Show::factory()->create(['imdb_id' => 'tt7654321', 'name' => 'Test Show']);
        $episode = Episode::factory()->for($show)->create(['season' => 1, 'number' => 1]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/torrent.php')) {
                return Http::response(fakeIptTorrentDetailPageWithoutImdb());
            }

            return Http::response(fakeIptSearchHtml([
                fakeIptTorrentRow(torrentId: 702, name: 'Test.Show.S01E01', seeders: 20),
            ]));
        });

        $service = new IptorrentsService;
        $result = $service->searchEpisodeByName($episode);

        expect($result)->toBeNull();
    });

    it('returns null when show has no IMDB ID', function () {
        $show = Show::factory()->create(['imdb_id' => '', 'name' => 'No IMDB Show']);
        $episode = Episode::factory()->for($show)->create(['season' => 1, 'number' => 2]);

        $service = new IptorrentsService;
        $result = $service->searchEpisodeByName($episode);

        expect($result)->toBeNull();
        Http::assertNothingSent();
    });

    it('sanitizes show name in search URL', function () {
        $show = Show::factory()->create(['imdb_id' => 'tt7654321', 'name' => "Widow's Bay"]);
        $episode = Episode::factory()->for($show)->create(['season' => 1, 'number' => 1]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/torrent.php')) {
                return Http::response(fakeIptTorrentDetailPage('tt7654321'));
            }

            return Http::response(fakeIptSearchHtml([
                fakeIptTorrentRow(torrentId: 704, name: 'Widows.Bay.S01E01.x265', seeders: 10),
            ]));
        });

        $service = new IptorrentsService;
        $service->searchEpisodeByName($episode);

        Http::assertSent(fn ($request) => ! str_contains($request->url(), '/torrent.php')
            && str_contains($request->url(), 'q=Widows+Bay+s01e01'));
    });

    it('returns null when search finds nothing', function () {
        $show = Show::factory()->create(['imdb_id' => 'tt7654321', 'name' => 'Ghost Show']);
        $episode = Episode::factory()->for($show)->create(['season' => 1, 'number' => 1]);

        Http::fake([
            'iptorrents.com/*' => Http::response(fakeIptSearchHtml([])),
        ]);

        $service = new IptorrentsService;
        $result = $service->searchEpisodeByName($episode);

        expect($result)->toBeNull();
        Http::assertSentCount(1);
    });

    it('finds match at second position and learns ipt_search_term', function () {
        $show = Show::factory()->create(['imdb_id' => 'tt2222222', 'name' => 'Taskmaster']);
        $episode = Episode::factory()->for($show)->create(['season' => 1, 'number' => 1]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/files')) {
                return Http::response(fakeIptFileListHtml());
            }

            if (str_contains($request->url(), '/torrent.php?id=900')) {
                return Http::response(fakeIptTorrentDetailPage('tt1111111'));
            }

            if (str_contains($request->url(), '/torrent.php?id=901')) {
                return Http::response(fakeIptTorrentDetailPage('tt2222222'));
            }

            if (str_contains($request->url(), '/torrent.php?id=950')) {
                return Http::response(fakeIptTorrentDetailPage('tt2222222'));
            }

            if (str_contains($request->url(), 'q=Taskmaster+AU+s01e01')) {
                return Http::response(fakeIptSearchHtml([
                    fakeIptTorrentRow(torrentId: 950, name: 'Taskmaster AU S01E01 1080p', seeders: 100),
                ]));
            }

            return Http::response(fakeIptSearchHtml([
                fakeIptTorrentRow(torrentId: 900, name: 'Taskmaster S01E01 1080p HEVC x265-MeGusta', seeders: 200),
                fakeIptTorrentRow(torrentId: 901, name: 'Taskmaster AU S01E01 1080p HEVC x265-MeGusta', seeders: 100),
            ]));
        });

        $service = new IptorrentsService;
        $result = $service->searchEpisodeByName($episode);

        expect($result)
            ->not->toBeNull()
            ->and($result['torrent_id'])->toBe(901);

        expect($show->fresh()->ipt_search_term)->toBe('Taskmaster AU');

        // 1 search + 2 IMDB lookups + 1 file-list check (id901) + 1 verification search + 1 verification IMDB
        Http::assertSentCount(6);
    });

    it('learns ipt_search_term when the only IMDB match at index > 0 is rar-packed', function () {
        $show = Show::factory()->create(['imdb_id' => 'tt2222222', 'name' => 'Taskmaster']);
        $episode = Episode::factory()->for($show)->create(['season' => 1, 'number' => 1]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/t/901/files')) {
                return Http::response(fakeIptFileListHtml(['taskmaster.au.s01e01.1080p.web.h265-grp.r00']));
            }

            if (str_contains($request->url(), '/torrent.php?id=900')) {
                return Http::response(fakeIptTorrentDetailPage('tt1111111'));
            }

            if (str_contains($request->url(), '/torrent.php?id=901')) {
                return Http::response(fakeIptTorrentDetailPage('tt2222222'));
            }

            if (str_contains($request->url(), '/torrent.php?id=950')) {
                return Http::response(fakeIptTorrentDetailPage('tt2222222'));
            }

            if (str_contains($request->url(), 'q=Taskmaster+AU+s01e01')) {
                return Http::response(fakeIptSearchHtml([
                    fakeIptTorrentRow(torrentId: 950, name: 'Taskmaster AU S01E01 1080p', seeders: 100),
                ]));
            }

            return Http::response(fakeIptSearchHtml([
                fakeIptTorrentRow(torrentId: 900, name: 'Taskmaster S01E01 1080p HEVC x265-MeGusta', seeders: 200),
                fakeIptTorrentRow(torrentId: 901, name: 'Taskmaster AU S01E01 1080p HEVC x265-MeGusta', seeders: 100),
            ]));
        });

        $service = new IptorrentsService;
        $result = $service->searchEpisodeByName($episode);

        expect($result)
            ->not->toBeNull()
            ->and($result['torrent_id'])->toBe(901);

        expect($show->fresh()->ipt_search_term)->toBe('Taskmaster AU');
    });

    it('uses ipt_search_term when set on show', function () {
        $show = Show::factory()->create(['imdb_id' => 'tt2222222', 'name' => 'Taskmaster', 'ipt_search_term' => 'Taskmaster AU']);
        $episode = Episode::factory()->for($show)->create(['season' => 1, 'number' => 1]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/torrent.php')) {
                return Http::response(fakeIptTorrentDetailPage('tt2222222'));
            }

            return Http::response(fakeIptSearchHtml([
                fakeIptTorrentRow(torrentId: 910, name: 'Taskmaster AU S01E01 1080p', seeders: 100),
            ]));
        });

        $service = new IptorrentsService;
        $result = $service->searchEpisodeByName($episode);

        expect($result)
            ->not->toBeNull()
            ->and($result['torrent_id'])->toBe(910);

        Http::assertSent(fn ($request) => ! str_contains($request->url(), '/torrent.php')
            && str_contains($request->url(), 'q=Taskmaster+AU+s01e01'));
    });

    it('does not overwrite existing ipt_search_term', function () {
        $show = Show::factory()->create(['imdb_id' => 'tt2222222', 'name' => 'Taskmaster', 'ipt_search_term' => 'Taskmaster AU']);
        $episode = Episode::factory()->for($show)->create(['season' => 1, 'number' => 1]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/torrent.php?id=920')) {
                return Http::response(fakeIptTorrentDetailPage('tt9999999'));
            }

            if (str_contains($request->url(), '/torrent.php?id=921')) {
                return Http::response(fakeIptTorrentDetailPage('tt2222222'));
            }

            return Http::response(fakeIptSearchHtml([
                fakeIptTorrentRow(torrentId: 920, name: 'Taskmaster AU S01E01 720p', seeders: 50),
                fakeIptTorrentRow(torrentId: 921, name: 'Taskmaster AU S01E01 1080p', seeders: 30),
            ]));
        });

        $service = new IptorrentsService;
        $result = $service->searchEpisodeByName($episode);

        expect($result['torrent_id'])->toBe(921);
        expect($show->fresh()->ipt_search_term)->toBe('Taskmaster AU');
    });

    it('does not learn when verification search returns wrong IMDB', function () {
        $show = Show::factory()->create(['imdb_id' => 'tt2222222', 'name' => 'Taskmaster']);
        $episode = Episode::factory()->for($show)->create(['season' => 1, 'number' => 1]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/files')) {
                return Http::response(fakeIptFileListHtml());
            }

            if (str_contains($request->url(), '/torrent.php?id=900')) {
                return Http::response(fakeIptTorrentDetailPage('tt1111111'));
            }

            if (str_contains($request->url(), '/torrent.php?id=901')) {
                return Http::response(fakeIptTorrentDetailPage('tt2222222'));
            }

            if (str_contains($request->url(), '/torrent.php?id=960')) {
                return Http::response(fakeIptTorrentDetailPage('tt9999999'));
            }

            if (str_contains($request->url(), 'q=Taskmaster+AU+s01e01')) {
                return Http::response(fakeIptSearchHtml([
                    fakeIptTorrentRow(torrentId: 960, name: 'Taskmaster AU S01E01 720p', seeders: 50),
                ]));
            }

            return Http::response(fakeIptSearchHtml([
                fakeIptTorrentRow(torrentId: 900, name: 'Taskmaster S01E01 1080p HEVC x265-MeGusta', seeders: 200),
                fakeIptTorrentRow(torrentId: 901, name: 'Taskmaster AU S01E01 1080p HEVC x265-MeGusta', seeders: 100),
            ]));
        });

        $service = new IptorrentsService;
        $result = $service->searchEpisodeByName($episode);

        expect($result)
            ->not->toBeNull()
            ->and($result['torrent_id'])->toBe(901);

        expect($show->fresh()->ipt_search_term)->toBeNull();

        // 1 search + 2 IMDB lookups + 1 file-list check (id901) + 1 verification search + 1 verification IMDB
        Http::assertSentCount(6);
    });

    it('does not learn when verification search returns no results', function () {
        $show = Show::factory()->create(['imdb_id' => 'tt2222222', 'name' => 'Taskmaster']);
        $episode = Episode::factory()->for($show)->create(['season' => 1, 'number' => 1]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/files')) {
                return Http::response(fakeIptFileListHtml());
            }

            if (str_contains($request->url(), '/torrent.php?id=900')) {
                return Http::response(fakeIptTorrentDetailPage('tt1111111'));
            }

            if (str_contains($request->url(), '/torrent.php?id=901')) {
                return Http::response(fakeIptTorrentDetailPage('tt2222222'));
            }

            if (str_contains($request->url(), 'q=Taskmaster+AU+s01e01')) {
                return Http::response(fakeIptSearchHtml([]));
            }

            return Http::response(fakeIptSearchHtml([
                fakeIptTorrentRow(torrentId: 900, name: 'Taskmaster S01E01 1080p HEVC x265-MeGusta', seeders: 200),
                fakeIptTorrentRow(torrentId: 901, name: 'Taskmaster AU S01E01 1080p HEVC x265-MeGusta', seeders: 100),
            ]));
        });

        $service = new IptorrentsService;
        $result = $service->searchEpisodeByName($episode);

        expect($result)
            ->not->toBeNull()
            ->and($result['torrent_id'])->toBe(901);

        expect($show->fresh()->ipt_search_term)->toBeNull();

        // 1 search + 2 IMDB lookups + 1 file-list check (id901) + 1 verification search (no IMDB lookup since empty)
        Http::assertSentCount(5);
    });

    it('does not learn when match is at first position', function () {
        $show = Show::factory()->create(['imdb_id' => 'tt7654321', 'name' => 'Test Show']);
        $episode = Episode::factory()->for($show)->create(['season' => 1, 'number' => 1]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/torrent.php')) {
                return Http::response(fakeIptTorrentDetailPage('tt7654321'));
            }

            return Http::response(fakeIptSearchHtml([
                fakeIptTorrentRow(torrentId: 930, name: 'Test Show S01E01 1080p', seeders: 80),
            ]));
        });

        $service = new IptorrentsService;
        $service->searchEpisodeByName($episode);

        expect($show->fresh()->ipt_search_term)->toBeNull();
    });

    it('caps IMDB lookups at maximum', function () {
        $show = Show::factory()->create(['imdb_id' => 'tt7654321', 'name' => 'Test Show']);
        $episode = Episode::factory()->for($show)->create(['season' => 1, 'number' => 1]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/torrent.php')) {
                return Http::response(fakeIptTorrentDetailPage('tt9999999'));
            }

            return Http::response(fakeIptSearchHtml([
                fakeIptTorrentRow(torrentId: 940, name: 'Wrong.1.S01E01', seeders: 100),
                fakeIptTorrentRow(torrentId: 941, name: 'Wrong.2.S01E01', seeders: 90),
                fakeIptTorrentRow(torrentId: 942, name: 'Wrong.3.S01E01', seeders: 80),
                fakeIptTorrentRow(torrentId: 943, name: 'Wrong.4.S01E01', seeders: 70),
                fakeIptTorrentRow(torrentId: 944, name: 'Wrong.5.S01E01', seeders: 60),
            ]));
        });

        $service = new IptorrentsService;
        $result = $service->searchEpisodeByName($episode);

        expect($result)->toBeNull();

        // 1 search + 3 IMDB lookups (capped, not all 5)
        Http::assertSentCount(4);
    });

    it('re-throws rate limit exception from learnSearchTerm', function () {
        $show = Show::factory()->create(['imdb_id' => 'tt2222222', 'name' => 'Taskmaster']);
        $episode = Episode::factory()->for($show)->create(['season' => 1, 'number' => 1]);

        $searchCallCount = 0;

        Http::fake(function ($request) use (&$searchCallCount) {
            if (str_contains($request->url(), '/torrent.php?id=900')) {
                return Http::response(fakeIptTorrentDetailPage('tt1111111'));
            }

            if (str_contains($request->url(), '/torrent.php?id=901')) {
                return Http::response(fakeIptTorrentDetailPage('tt2222222'));
            }

            if (! str_contains($request->url(), '/torrent.php')) {
                $searchCallCount++;

                if ($searchCallCount === 1) {
                    return Http::response(fakeIptSearchHtml([
                        fakeIptTorrentRow(torrentId: 900, name: 'Taskmaster S01E01 1080p HEVC x265-MeGusta', seeders: 200),
                        fakeIptTorrentRow(torrentId: 901, name: 'Taskmaster AU S01E01 1080p HEVC x265-MeGusta', seeders: 100),
                    ]));
                }
            }

            return Http::response(fakeIptSearchHtml([]));
        });

        seedIptCooldown();

        $service = new IptorrentsService;

        expect(fn () => $service->searchEpisodeByName($episode))
            ->toThrow(IptorrentsRateLimitExceededException::class);
    });

    it('extracts show title from single-digit season/episode naming', function () {
        $service = new IptorrentsService;
        $method = new ReflectionMethod($service, 'extractShowTitle');

        expect($method->invoke($service, 'Some Show S1E1 720p'))->toBe('Some Show')
            ->and($method->invoke($service, 'Another Show S1E12 1080p'))->toBe('Another Show')
            ->and($method->invoke($service, 'Third Show S12E1 HDTV'))->toBe('Third Show');
    });

    it('extracts show title from date-based episode naming', function () {
        $service = new IptorrentsService;
        $method = new ReflectionMethod($service, 'extractShowTitle');

        expect($method->invoke($service, 'Daily Show 2024.11.25 720p'))->toBe('Daily Show')
            ->and($method->invoke($service, 'Late Night 2024-01-15 1080p'))->toBe('Late Night');
    });

    it('uses raw DB name for search when display name has country suffix', function () {
        $network = fn (string $code): array => [
            'id' => 1,
            'name' => 'Net',
            'country' => ['name' => 'X', 'code' => $code, 'timezone' => 'UTC'],
        ];

        Show::factory()->create(['name' => 'Taskmaster', 'network' => $network('GB')]);
        $show = Show::factory()->create([
            'imdb_id' => 'tt2222222',
            'name' => 'Taskmaster',
            'network' => $network('AU'),
        ]);
        Show::recomputeAmbiguousNames();

        $episode = Episode::factory()->for($show)->create(['season' => 1, 'number' => 1]);

        expect($show->name)->toBe('Taskmaster AU');

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/torrent.php')) {
                return Http::response(fakeIptTorrentDetailPage('tt2222222'));
            }

            return Http::response(fakeIptSearchHtml([
                fakeIptTorrentRow(torrentId: 970, name: 'Taskmaster S01E01 1080p', seeders: 50),
            ]));
        });

        $service = new IptorrentsService;
        $result = $service->searchEpisodeByName($episode);

        expect($result)
            ->not->toBeNull()
            ->and($result['torrent_id'])->toBe(970);

        Http::assertSent(fn ($request) => ! str_contains($request->url(), '/torrent.php')
            && str_contains($request->url(), 'q=Taskmaster+s01e01')
            && ! str_contains($request->url(), 'Taskmaster+AU'));
    });

    it('skips learning when extracted title matches raw DB name', function () {
        $network = fn (string $code): array => [
            'id' => 1,
            'name' => 'Net',
            'country' => ['name' => 'X', 'code' => $code, 'timezone' => 'UTC'],
        ];

        Show::factory()->create(['name' => 'Taskmaster', 'network' => $network('GB')]);
        $show = Show::factory()->create([
            'imdb_id' => 'tt2222222',
            'name' => 'Taskmaster',
            'network' => $network('AU'),
        ]);
        Show::recomputeAmbiguousNames();

        $episode = Episode::factory()->for($show)->create(['season' => 1, 'number' => 1]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/files')) {
                return Http::response(fakeIptFileListHtml());
            }

            if (str_contains($request->url(), '/torrent.php?id=980')) {
                return Http::response(fakeIptTorrentDetailPage('tt1111111'));
            }

            if (str_contains($request->url(), '/torrent.php?id=981')) {
                return Http::response(fakeIptTorrentDetailPage('tt2222222'));
            }

            return Http::response(fakeIptSearchHtml([
                fakeIptTorrentRow(torrentId: 980, name: 'Wrong.Show.S01E01.1080p', seeders: 200),
                fakeIptTorrentRow(torrentId: 981, name: 'Taskmaster S01E01 1080p', seeders: 100),
            ]));
        });

        $service = new IptorrentsService;
        $service->searchEpisodeByName($episode);

        expect($show->fresh()->ipt_search_term)->toBeNull();

        // 1 search + 2 IMDB lookups + 1 file-list check (id981). No verification
        // search because extracted title "Taskmaster" matches the raw DB name.
        Http::assertSentCount(4);
    });
});
