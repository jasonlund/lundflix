<?php

use App\Enums\EpisodeType;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\Show;
use App\Services\IptorrentsService;
use App\Services\Torrent\Kind;
use App\Services\Torrent\RequestDownloadPlanner;
use App\Services\Torrent\ResolveResult;
use App\Services\Torrent\TorrentRequest;
use App\Services\Torrent\TorrentResolver;
use Mockery\MockInterface;

function makePlannerTorrentMatch(int $id = 100, string $size = '4 GB', string $filename = 'movie.torrent'): array
{
    return [
        'torrent_id' => $id,
        'name' => "Match.{$id}.1080p",
        'size' => $size,
        'seeders' => 10,
        'leechers' => 1,
        'snatches' => 5,
        'uploaded' => 'now',
        'download_url' => "https://iptorrents.com/download.php/{$id}/{$filename}",
    ];
}

function makePlannerMovie(int $id, string $imdb = 'tt0000001'): Movie
{
    $movie = new Movie(['imdb_id' => $imdb, 'title' => "Movie {$id}", 'year' => 2024]);
    $movie->id = $id;
    $movie->exists = true;

    return $movie;
}

function makePlannerShow(int $id, string $imdb = 'tt9000001', string $name = 'Test Show'): Show
{
    $show = new Show(['imdb_id' => $imdb, 'name' => $name]);
    $show->id = $id;
    $show->exists = true;

    return $show;
}

function makePlannerEpisode(int $id, Show $show, int $season, int $number, string $airdate = '2024-01-01', EpisodeType $type = EpisodeType::Regular): Episode
{
    $episode = new Episode([
        'show_id' => $show->id,
        'season' => $season,
        'number' => $number,
        'type' => $type->value,
        'airdate' => $airdate,
    ]);
    $episode->id = $id;
    $episode->exists = true;
    $episode->setRelation('show', $show);

    return $episode;
}

/**
 * @param  list<Episode>  $seasonEpisodes  All show episodes (any season) attached to the show
 */
function makePlannerRequest(array $items, array $seasonEpisodes = []): Request
{
    $request = new Request;
    $request->id = 1;
    $request->exists = true;

    // Attach episodes to each show found in items so isFullSeason() can compute.
    $showsSeen = [];
    foreach ($items as $item) {
        $target = $item->requestable;
        if ($target instanceof Episode && $target->show && ! isset($showsSeen[$target->show->id])) {
            $showsSeen[$target->show->id] = $target->show;
        }
    }
    foreach ($showsSeen as $show) {
        $show->setRelation('episodes', collect($seasonEpisodes)->filter(
            fn (Episode $e): bool => $e->show_id === $show->id,
        )->values());
    }

    $request->setRelation('items', collect($items));

    return $request;
}

function makePlannerItem(int $id, Movie|Episode $requestable): RequestItem
{
    $item = new RequestItem;
    $item->id = $id;
    $item->exists = true;
    $item->setAttribute('requestable_type', $requestable::class);
    $item->setAttribute('requestable_id', $requestable->id);
    $item->setRelation('requestable', $requestable);

    return $item;
}

function mockPlanner(callable $resolverSetup, ?callable $iptSetup = null): RequestDownloadPlanner
{
    $resolver = Mockery::mock(TorrentResolver::class);
    $resolverSetup($resolver);

    $ipt = Mockery::mock(IptorrentsService::class);
    if ($iptSetup) {
        $iptSetup($ipt);
    } else {
        $ipt->shouldReceive('searchMultiSeasonPack')->andReturnNull()->byDefault();
    }

    return new RequestDownloadPlanner($resolver, $ipt);
}

it('returns empty PlanResult for empty request', function () {
    $planner = mockPlanner(fn (MockInterface $r) => $r->shouldNotReceive('resolveDetailed'));

    $request = makePlannerRequest([]);

    $plan = $planner->plan($request);

    expect($plan->downloads)->toBe([])
        ->and($plan->notFound)->toBe([])
        ->and($plan->oversize)->toBe([])
        ->and($plan->multiSeasonReview)->toBe([])
        ->and($plan->packCovered)->toBe([]);
});

it('resolves a single movie to downloads', function () {
    $movie = makePlannerMovie(1);
    $item = makePlannerItem(1, $movie);

    $planner = mockPlanner(function (MockInterface $r) {
        $r->shouldReceive('resolveDetailed')
            ->once()
            ->with(Mockery::on(fn (TorrentRequest $tr): bool => $tr->kind === Kind::Movie))
            ->andReturn(new ResolveResult(makePlannerTorrentMatch(42, '4 GB', 'm.torrent'), false));
    });

    $plan = $planner->plan(makePlannerRequest([$item]));

    expect($plan->downloads)->toHaveCount(1)
        ->and($plan->downloads[0]['torrent_id'])->toBe(42)
        ->and($plan->downloads[0]['filename'])->toBe('m.torrent')
        ->and($plan->notFound)->toBe([])
        ->and($plan->oversize)->toBe([]);
});

it('classifies movie miss with oversize signal as oversize', function () {
    $movie = makePlannerMovie(1);
    $item = makePlannerItem(1, $movie);

    $planner = mockPlanner(function (MockInterface $r) {
        $r->shouldReceive('resolveDetailed')->once()
            ->andReturn(new ResolveResult(null, true));
    });

    $plan = $planner->plan(makePlannerRequest([$item]));

    expect($plan->oversize)->toHaveCount(1)
        ->and($plan->notFound)->toBe([])
        ->and($plan->downloads)->toBe([]);
});

it('classifies movie miss without oversize signal as not found', function () {
    $movie = makePlannerMovie(1);
    $item = makePlannerItem(1, $movie);

    $planner = mockPlanner(function (MockInterface $r) {
        $r->shouldReceive('resolveDetailed')->once()
            ->andReturn(new ResolveResult(null, false));
    });

    $plan = $planner->plan(makePlannerRequest([$item]));

    expect($plan->notFound)->toHaveCount(1)
        ->and($plan->oversize)->toBe([])
        ->and($plan->downloads)->toBe([]);
});

it('partitions multiple movies correctly', function () {
    $m1 = makePlannerMovie(1, 'tt0000001');
    $m2 = makePlannerMovie(2, 'tt0000002');
    $m3 = makePlannerMovie(3, 'tt0000003');

    $items = [
        makePlannerItem(1, $m1),
        makePlannerItem(2, $m2),
        makePlannerItem(3, $m3),
    ];

    $planner = mockPlanner(function (MockInterface $r) {
        $r->shouldReceive('resolveDetailed')->times(3)
            ->andReturn(
                new ResolveResult(makePlannerTorrentMatch(101), false),
                new ResolveResult(null, true),
                new ResolveResult(null, false),
            );
    });

    $plan = $planner->plan(makePlannerRequest($items));

    expect($plan->downloads)->toHaveCount(1)
        ->and($plan->oversize)->toHaveCount(1)
        ->and($plan->notFound)->toHaveCount(1);
});

it('uses season pack when full season and pack hits', function () {
    $show = makePlannerShow(10);
    $e1 = makePlannerEpisode(101, $show, 1, 1);
    $e2 = makePlannerEpisode(102, $show, 1, 2);

    $items = [makePlannerItem(1, $e1), makePlannerItem(2, $e2)];

    $planner = mockPlanner(function (MockInterface $r) {
        $r->shouldReceive('resolveDetailed')
            ->once()
            ->with(Mockery::on(fn (TorrentRequest $tr): bool => $tr->kind === Kind::SeasonPack))
            ->andReturn(new ResolveResult(makePlannerTorrentMatch(500, '8 GB', 's1.torrent'), false));
    });

    $plan = $planner->plan(makePlannerRequest($items, [$e1, $e2]));

    expect($plan->downloads)->toHaveCount(1)
        ->and($plan->downloads[0]['torrent_id'])->toBe(500)
        ->and($plan->packCovered)->toHaveCount(2)
        ->and($plan->notFound)->toBe([]);
});

it('falls back to per-episode when full-season pack misses', function () {
    $show = makePlannerShow(11);
    $e1 = makePlannerEpisode(201, $show, 1, 1);
    $e2 = makePlannerEpisode(202, $show, 1, 2);
    $items = [makePlannerItem(1, $e1), makePlannerItem(2, $e2)];

    $planner = mockPlanner(function (MockInterface $r) {
        $r->shouldReceive('resolveDetailed')
            ->with(Mockery::on(fn (TorrentRequest $tr): bool => $tr->kind === Kind::SeasonPack))
            ->once()
            ->andReturn(new ResolveResult(null, false));

        $r->shouldReceive('resolveDetailed')
            ->with(Mockery::on(fn (TorrentRequest $tr): bool => $tr->kind === Kind::Episode))
            ->times(2)
            ->andReturn(
                new ResolveResult(makePlannerTorrentMatch(601, '2 GB', 'ep1.torrent'), false),
                new ResolveResult(null, false),
            );
    });

    $plan = $planner->plan(makePlannerRequest($items, [$e1, $e2]));

    expect($plan->downloads)->toHaveCount(1)
        ->and($plan->downloads[0]['torrent_id'])->toBe(601)
        ->and($plan->notFound)->toHaveCount(1)
        ->and($plan->packCovered)->toBe([]);
});

it('fires multi-season pack search only for full-season episode misses', function () {
    $show = makePlannerShow(12);
    $e1 = makePlannerEpisode(301, $show, 2, 1);
    $e2 = makePlannerEpisode(302, $show, 2, 2);
    $items = [makePlannerItem(1, $e1), makePlannerItem(2, $e2)];

    $planner = mockPlanner(
        function (MockInterface $r) {
            $r->shouldReceive('resolveDetailed')
                ->with(Mockery::on(fn (TorrentRequest $tr): bool => $tr->kind === Kind::SeasonPack))
                ->once()
                ->andReturn(new ResolveResult(null, false));

            $r->shouldReceive('resolveDetailed')
                ->with(Mockery::on(fn (TorrentRequest $tr): bool => $tr->kind === Kind::Episode))
                ->times(2)
                ->andReturn(
                    new ResolveResult(null, false),
                    new ResolveResult(null, false),
                );
        },
        function (MockInterface $i) use ($show) {
            $i->shouldReceive('searchMultiSeasonPack')
                ->once()
                ->with(Mockery::on(fn (Show $s): bool => $s->id === $show->id), 2)
                ->andReturn(makePlannerTorrentMatch(900, '120 GB', 'pack.torrent'));
        },
    );

    $plan = $planner->plan(makePlannerRequest($items, [$e1, $e2]));

    expect($plan->multiSeasonReview)->toHaveCount(1)
        ->and($plan->multiSeasonReview[0]['season'])->toBe(2)
        ->and($plan->multiSeasonReview[0]['requestItems'])->toHaveCount(2)
        ->and($plan->notFound)->toHaveCount(2);
});

it('does NOT call multi-season search on partial-season episode requests', function () {
    $show = makePlannerShow(13);
    $e1 = makePlannerEpisode(401, $show, 1, 1);
    $e2 = makePlannerEpisode(402, $show, 1, 2);
    $e3 = makePlannerEpisode(403, $show, 1, 3);

    // Only requesting e1 and e2 — not full season (e3 also aired).
    $items = [makePlannerItem(1, $e1), makePlannerItem(2, $e2)];

    $planner = mockPlanner(
        function (MockInterface $r) {
            // No season-pack call — not a full season.
            $r->shouldReceive('resolveDetailed')
                ->with(Mockery::on(fn (TorrentRequest $tr): bool => $tr->kind === Kind::SeasonPack))
                ->never();

            $r->shouldReceive('resolveDetailed')
                ->with(Mockery::on(fn (TorrentRequest $tr): bool => $tr->kind === Kind::Episode))
                ->times(2)
                ->andReturn(new ResolveResult(null, false), new ResolveResult(null, false));
        },
        function (MockInterface $i) {
            $i->shouldReceive('searchMultiSeasonPack')->never();
        },
    );

    $plan = $planner->plan(makePlannerRequest($items, [$e1, $e2, $e3]));

    expect($plan->multiSeasonReview)->toBe([])
        ->and($plan->notFound)->toHaveCount(2);
});

it('dedupes downloads by torrent_id', function () {
    $m1 = makePlannerMovie(1, 'tt0000001');
    $m2 = makePlannerMovie(2, 'tt0000002');
    $items = [makePlannerItem(1, $m1), makePlannerItem(2, $m2)];

    $planner = mockPlanner(function (MockInterface $r) {
        $r->shouldReceive('resolveDetailed')->times(2)
            ->andReturn(
                new ResolveResult(makePlannerTorrentMatch(777, '4 GB', 'a.torrent'), false),
                new ResolveResult(makePlannerTorrentMatch(777, '4 GB', 'a.torrent'), false),
            );
    });

    $plan = $planner->plan(makePlannerRequest($items));

    expect($plan->downloads)->toHaveCount(1);
});

it('excludes unaired episodes from full-season detection', function () {
    $show = makePlannerShow(14);
    $aired = makePlannerEpisode(501, $show, 1, 1, '2020-01-01');
    $unaired = makePlannerEpisode(502, $show, 1, 2, '2999-01-01');

    $items = [makePlannerItem(1, $aired)];

    $planner = mockPlanner(function (MockInterface $r) {
        // Only requesting aired episode → full season → try pack first.
        $r->shouldReceive('resolveDetailed')
            ->with(Mockery::on(fn (TorrentRequest $tr): bool => $tr->kind === Kind::SeasonPack))
            ->once()
            ->andReturn(new ResolveResult(makePlannerTorrentMatch(800, '6 GB', 'pack.torrent'), false));
    });

    $plan = $planner->plan(makePlannerRequest($items, [$aired, $unaired]));

    expect($plan->downloads)->toHaveCount(1)
        ->and($plan->packCovered)->toHaveCount(1);
});

it('excludes specials from full-season detection', function () {
    $show = makePlannerShow(15);
    $regular = makePlannerEpisode(601, $show, 1, 1, '2020-01-01');
    $special = makePlannerEpisode(602, $show, 1, 99, '2020-01-02', EpisodeType::SignificantSpecial);

    $items = [makePlannerItem(1, $regular)];

    $planner = mockPlanner(function (MockInterface $r) {
        // Special doesn't count — regular alone is full season → pack attempt.
        $r->shouldReceive('resolveDetailed')
            ->with(Mockery::on(fn (TorrentRequest $tr): bool => $tr->kind === Kind::SeasonPack))
            ->once()
            ->andReturn(new ResolveResult(makePlannerTorrentMatch(810, '6 GB', 'pack.torrent'), false));
    });

    $plan = $planner->plan(makePlannerRequest($items, [$regular, $special]));

    expect($plan->downloads)->toHaveCount(1)
        ->and($plan->packCovered)->toHaveCount(1);
});

it('requesting only specials is not treated as full season', function () {
    $show = makePlannerShow(16);
    $regular = makePlannerEpisode(701, $show, 1, 1, '2020-01-01');
    $special = makePlannerEpisode(702, $show, 1, 99, '2020-01-02', EpisodeType::SignificantSpecial);

    $items = [makePlannerItem(1, $special)];

    $planner = mockPlanner(function (MockInterface $r) {
        $r->shouldReceive('resolveDetailed')
            ->with(Mockery::on(fn (TorrentRequest $tr): bool => $tr->kind === Kind::SeasonPack))
            ->never();

        $r->shouldReceive('resolveDetailed')
            ->with(Mockery::on(fn (TorrentRequest $tr): bool => $tr->kind === Kind::Episode))
            ->once()
            ->andReturn(new ResolveResult(makePlannerTorrentMatch(900, '2 GB', 'sp.torrent'), false));
    });

    $plan = $planner->plan(makePlannerRequest($items, [$regular, $special]));

    expect($plan->downloads)->toHaveCount(1);
});

it('handles mixed request with movies + multiple shows + full + partial seasons', function () {
    $movie = makePlannerMovie(1);
    $showA = makePlannerShow(20, 'tt9000020', 'Show A');
    $a1 = makePlannerEpisode(2001, $showA, 1, 1, '2020-01-01');
    $a2 = makePlannerEpisode(2002, $showA, 1, 2, '2020-01-02');

    $showB = makePlannerShow(21, 'tt9000021', 'Show B');
    $b1 = makePlannerEpisode(2101, $showB, 1, 1, '2020-01-01');
    $b2 = makePlannerEpisode(2102, $showB, 1, 2, '2020-01-02');

    // Full season for show A, partial for show B (only b1).
    $items = [
        makePlannerItem(1, $movie),
        makePlannerItem(2, $a1),
        makePlannerItem(3, $a2),
        makePlannerItem(4, $b1),
    ];

    $planner = mockPlanner(function (MockInterface $r) {
        // Movie
        $r->shouldReceive('resolveDetailed')
            ->with(Mockery::on(fn (TorrentRequest $tr): bool => $tr->kind === Kind::Movie))
            ->once()
            ->andReturn(new ResolveResult(makePlannerTorrentMatch(1, '4 GB', 'mv.torrent'), false));

        // Show A: full season → pack hit
        $r->shouldReceive('resolveDetailed')
            ->with(Mockery::on(fn (TorrentRequest $tr): bool => $tr->kind === Kind::SeasonPack))
            ->once()
            ->andReturn(new ResolveResult(makePlannerTorrentMatch(2, '8 GB', 'pack.torrent'), false));

        // Show B: partial → episode resolve, hit
        $r->shouldReceive('resolveDetailed')
            ->with(Mockery::on(fn (TorrentRequest $tr): bool => $tr->kind === Kind::Episode))
            ->once()
            ->andReturn(new ResolveResult(makePlannerTorrentMatch(3, '2 GB', 'ep.torrent'), false));
    });

    $plan = $planner->plan(makePlannerRequest($items, [$a1, $a2, $b1, $b2]));

    expect($plan->downloads)->toHaveCount(3)
        ->and($plan->packCovered)->toHaveCount(2);
});
