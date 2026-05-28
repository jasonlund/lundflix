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

it('throws IptorrentsRateLimitExceededException when rate limit exceeded', function () {
    foreach (range(1, 20) as $_) {
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

        Http::assertSentCount(1);
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

    it('throws rate limit exception when exhausted', function () {
        foreach (range(1, 20) as $_) {
            RateLimiter::hit('iptorrents', 60);
        }

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

        Http::assertSentCount(2);
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

        Http::assertSentCount(3);
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
                fakeIptTorrentRow(torrentId: 815, name: 'Wrong.6.2024', seeders: 50),
            ]));
        });

        $service = new IptorrentsService;
        $result = $service->searchMovieByName($movie);

        expect($result)->toBeNull();

        // 1 search + 5 IMDB lookups (capped at MAX_IMDB_LOOKUPS=5, 6th row skipped)
        Http::assertSentCount(6);
    });
});

describe('searchEpisodeByName', function () {
    it('returns verified result when name search finds match', function () {
        $show = Show::factory()->create(['imdb_id' => 'tt7654321', 'name' => 'Test Show']);
        $episode = Episode::factory()->for($show)->create(['season' => 1, 'number' => 5]);

        Http::fake(function ($request) {
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

        Http::assertSentCount(2);
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

    it('finds match at second position and learns ipt_search_terms', function () {
        $show = Show::factory()->create(['imdb_id' => 'tt2222222', 'name' => 'Taskmaster']);
        $episode = Episode::factory()->for($show)->create(['season' => 1, 'number' => 1]);

        Http::fake(function ($request) {
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

        expect($show->fresh()->ipt_search_terms)->toBe(['Taskmaster AU']);

        // 1 search + 2 IMDB lookups + 1 verification search + 1 verification IMDB
        Http::assertSentCount(5);
    });

    it('uses ipt_search_terms when set on show', function () {
        $show = Show::factory()->create(['imdb_id' => 'tt2222222', 'name' => 'Taskmaster', 'ipt_search_terms' => ['Taskmaster AU']]);
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

    it('OR-combines multiple ipt_search_terms in a single query', function () {
        $show = Show::factory()->create([
            'imdb_id' => 'tt2222222',
            'name' => 'Taskmaster',
            'ipt_search_terms' => ['Taskmaster AU', 'Taskmaster Australia'],
        ]);
        $episode = Episode::factory()->for($show)->create(['season' => 1, 'number' => 1]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/torrent.php')) {
                return Http::response(fakeIptTorrentDetailPage('tt2222222'));
            }

            return Http::response(fakeIptSearchHtml([
                fakeIptTorrentRow(torrentId: 915, name: 'Taskmaster Australia S01E01 1080p', seeders: 100),
            ]));
        });

        $service = new IptorrentsService;
        $service->searchEpisodeByName($episode);

        Http::assertSent(fn ($request) => ! str_contains($request->url(), '/torrent.php')
            && str_contains($request->url(), 'q=%22Taskmaster+AU%22%7C%22Taskmaster+Australia%22+s01e01'));
    });

    it('does not learn when ipt_search_terms is already set', function () {
        $show = Show::factory()->create(['imdb_id' => 'tt2222222', 'name' => 'Taskmaster', 'ipt_search_terms' => ['Taskmaster AU']]);
        $episode = Episode::factory()->for($show)->create(['season' => 1, 'number' => 1]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/torrent.php?id=920')) {
                return Http::response(fakeIptTorrentDetailPage('tt9999999'));
            }

            if (str_contains($request->url(), '/torrent.php?id=921')) {
                return Http::response(fakeIptTorrentDetailPage('tt2222222'));
            }

            return Http::response(fakeIptSearchHtml([
                fakeIptTorrentRow(torrentId: 920, name: 'Wrong Show S01E01 720p', seeders: 50),
                fakeIptTorrentRow(torrentId: 921, name: 'Taskmaster Australia S01E01 1080p', seeders: 30),
            ]));
        });

        $service = new IptorrentsService;
        $result = $service->searchEpisodeByName($episode);

        expect($result['torrent_id'])->toBe(921);
        expect($show->fresh()->ipt_search_terms)->toBe(['Taskmaster AU']);
    });

    it('skips siblings sharing a prefix already known to mismatch', function () {
        $show = Show::factory()->create(['imdb_id' => 'tt3333333', 'name' => 'Taskmaster NZ']);
        $episode = Episode::factory()->for($show)->create(['season' => 5, 'number' => 3]);

        $imdbLookups = [];

        Http::fake(function ($request) use (&$imdbLookups) {
            if (preg_match('#/torrent\.php\?id=(\d+)#', $request->url(), $m)) {
                $imdbLookups[] = (int) $m[1];

                // 970/971/972 share prefix "Taskmaster AU" → AU imdb
                if (in_array((int) $m[1], [970, 971, 972], true)) {
                    return Http::response(fakeIptTorrentDetailPage('tt2222222'));
                }

                // 973 is "Taskmaster" UK
                if ((int) $m[1] === 973) {
                    return Http::response(fakeIptTorrentDetailPage('tt1111111'));
                }

                // 974 is the NZ match
                return Http::response(fakeIptTorrentDetailPage('tt3333333'));
            }

            return Http::response(fakeIptSearchHtml([
                fakeIptTorrentRow(torrentId: 970, name: 'Taskmaster AU S05E03 1080p', seeders: 500),
                fakeIptTorrentRow(torrentId: 971, name: 'Taskmaster AU S05E03 720p', seeders: 400),
                fakeIptTorrentRow(torrentId: 972, name: 'Taskmaster AU S05E03 HEVC', seeders: 300),
                fakeIptTorrentRow(torrentId: 973, name: 'Taskmaster S05E03 1080p', seeders: 250),
                fakeIptTorrentRow(torrentId: 974, name: 'Taskmaster NZ S05E03 1080p', seeders: 100),
            ]));
        });

        $service = new IptorrentsService;
        $result = $service->searchEpisodeByName($episode);

        expect($result)
            ->not->toBeNull()
            ->and($result['torrent_id'])->toBe(974);

        // Only 970 (AU), 973 (UK), 974 (NZ) fetched — 971/972 deduped by prefix
        expect($imdbLookups)->toBe([970, 973, 974]);
    });

    it('does not learn when verification search returns wrong IMDB', function () {
        $show = Show::factory()->create(['imdb_id' => 'tt2222222', 'name' => 'Taskmaster']);
        $episode = Episode::factory()->for($show)->create(['season' => 1, 'number' => 1]);

        Http::fake(function ($request) {
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

        expect($show->fresh()->ipt_search_terms)->toBeNull();

        // 1 search + 2 IMDB lookups + 1 verification search + 1 verification IMDB
        Http::assertSentCount(5);
    });

    it('does not learn when verification search returns no results', function () {
        $show = Show::factory()->create(['imdb_id' => 'tt2222222', 'name' => 'Taskmaster']);
        $episode = Episode::factory()->for($show)->create(['season' => 1, 'number' => 1]);

        Http::fake(function ($request) {
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

        expect($show->fresh()->ipt_search_terms)->toBeNull();

        // 1 search + 2 IMDB lookups + 1 verification search (no IMDB lookup since empty)
        Http::assertSentCount(4);
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

        expect($show->fresh()->ipt_search_terms)->toBeNull();
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
                fakeIptTorrentRow(torrentId: 945, name: 'Wrong.6.S01E01', seeders: 50),
            ]));
        });

        $service = new IptorrentsService;
        $result = $service->searchEpisodeByName($episode);

        expect($result)->toBeNull();

        // 1 search + 5 IMDB lookups (capped at MAX_IMDB_LOOKUPS=5, 6th row skipped)
        Http::assertSentCount(6);
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

        foreach (range(1, 17) as $_) {
            RateLimiter::hit('iptorrents', 60);
        }

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
});

describe('searchSeasonPack', function () {
    it('queries IMDB + season token under TV pack categories', function () {
        $show = Show::factory()->create(['imdb_id' => 'tt8888888', 'name' => 'Show']);

        Http::fake([
            'iptorrents.com/*' => Http::response(fakeIptSearchHtml([
                fakeIptTorrentRow(torrentId: 1, name: 'Show.S03.1080p'),
            ])),
        ]);

        $service = new IptorrentsService;
        $result = $service->searchSeasonPack($show, 3);

        expect($result['torrent_id'])->toBe(1);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'q=tt8888888+S03')
            && str_contains($r->url(), '65=')
            && str_contains($r->url(), '83='));
    });

    it('returns null when show has no IMDB ID', function () {
        $show = Show::factory()->create(['imdb_id' => '', 'name' => 'Show']);

        $service = new IptorrentsService;
        expect($service->searchSeasonPack($show, 3))->toBeNull();
        Http::assertNothingSent();
    });
});

describe('searchSeasonPackByName', function () {
    it('IMDB-verifies pack and returns matching result', function () {
        $show = Show::factory()->create(['imdb_id' => 'tt9991111', 'name' => 'Real Show']);

        Http::fake(function ($req) {
            if (preg_match('#/torrent\.php\?id=(\d+)#', $req->url(), $m)) {
                return Http::response(fakeIptTorrentDetailPage(
                    (int) $m[1] === 500 ? 'tt0000000' : 'tt9991111'
                ));
            }

            return Http::response(fakeIptSearchHtml([
                fakeIptTorrentRow(torrentId: 500, name: 'Wrong Show S03 1080p', seeders: 200),
                fakeIptTorrentRow(torrentId: 502, name: 'Real Show S03 1080p', seeders: 100),
            ]));
        });

        $service = new IptorrentsService;
        $result = $service->searchSeasonPackByName($show, 3);

        expect($result['torrent_id'])->toBe(502);
    });

    it('caps IMDB lookups at MAX_IMDB_LOOKUPS', function () {
        $show = Show::factory()->create(['imdb_id' => 'tt9991111', 'name' => 'Show']);

        Http::fake(function ($req) {
            if (str_contains($req->url(), '/torrent.php')) {
                return Http::response(fakeIptTorrentDetailPage('tt0000000'));
            }

            return Http::response(fakeIptSearchHtml([
                fakeIptTorrentRow(torrentId: 600, name: 'Wrong.A S03 720p'),
                fakeIptTorrentRow(torrentId: 601, name: 'Wrong.B S03 720p'),
                fakeIptTorrentRow(torrentId: 602, name: 'Wrong.C S03 720p'),
                fakeIptTorrentRow(torrentId: 603, name: 'Wrong.D S03 720p'),
                fakeIptTorrentRow(torrentId: 604, name: 'Wrong.E S03 720p'),
                fakeIptTorrentRow(torrentId: 605, name: 'Wrong.F S03 720p'),
            ]));
        });

        $service = new IptorrentsService;
        expect($service->searchSeasonPackByName($show, 3))->toBeNull();
        Http::assertSentCount(6);
    });
});

describe('searchMultiSeasonPack', function () {
    it('returns range pack covering target season', function () {
        $show = Show::factory()->create(['imdb_id' => 'tt7777777', 'name' => 'Survivor']);

        Http::fake([
            'iptorrents.com/*' => Http::response(fakeIptSearchHtml([
                fakeIptTorrentRow(torrentId: 1, name: 'Survivor.S01-S38.1080p', size: '500 GB', seeders: 50),
            ])),
        ]);

        $service = new IptorrentsService;
        $result = $service->searchMultiSeasonPack($show, 12);

        expect($result['torrent_id'])->toBe(1);
    });

    it('returns complete-series pack for any season', function () {
        $show = Show::factory()->create(['imdb_id' => 'tt7777777', 'name' => 'Show']);

        Http::fake([
            'iptorrents.com/*' => Http::response(fakeIptSearchHtml([
                fakeIptTorrentRow(torrentId: 2, name: 'The.Wire.Complete.Series.1080p', size: '300 GB'),
            ])),
        ]);

        $service = new IptorrentsService;
        expect($service->searchMultiSeasonPack($show, 1)['torrent_id'])->toBe(2);
        // Verify against a non-existent season too
    });

    it('skips single-season pack matching target', function () {
        $show = Show::factory()->create(['imdb_id' => 'tt7777777', 'name' => 'Show']);

        Http::fake([
            'iptorrents.com/*' => Http::response(fakeIptSearchHtml([
                fakeIptTorrentRow(torrentId: 3, name: 'Show.S03.1080p'),
            ])),
        ]);

        $service = new IptorrentsService;
        expect($service->searchMultiSeasonPack($show, 3))->toBeNull();
    });

    it('returns null when no result covers season', function () {
        $show = Show::factory()->create(['imdb_id' => 'tt7777777', 'name' => 'Show']);

        Http::fake([
            'iptorrents.com/*' => Http::response(fakeIptSearchHtml([
                fakeIptTorrentRow(torrentId: 4, name: 'Show.S04-S06.1080p', size: '60 GB'),
            ])),
        ]);

        $service = new IptorrentsService;
        expect($service->searchMultiSeasonPack($show, 1))->toBeNull();
    });

    it('does NOT apply size cap', function () {
        $show = Show::factory()->create(['imdb_id' => 'tt7777777', 'name' => 'Show']);

        Http::fake([
            'iptorrents.com/*' => Http::response(fakeIptSearchHtml([
                fakeIptTorrentRow(torrentId: 5, name: 'Show.S01-S10.4K', size: '500 GB'),
            ])),
        ]);

        $service = new IptorrentsService;
        $result = $service->searchMultiSeasonPack($show, 5);

        expect($result['torrent_id'])->toBe(5);
    });

    it('returns null when show has no IMDB', function () {
        $show = Show::factory()->create(['imdb_id' => '', 'name' => 'Show']);

        $service = new IptorrentsService;
        expect($service->searchMultiSeasonPack($show, 1))->toBeNull();
        Http::assertNothingSent();
    });
});
