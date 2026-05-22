<?php

declare(strict_types=1);

use App\Models\Episode;
use App\Support\EpisodeGrouping;
use Illuminate\Support\Collection;

function makeEpisode(int $id, int $season, int $number, ?string $airdate = null, ?int $tvmazeId = null): Episode
{
    $episode = new Episode([
        'season' => $season,
        'number' => $number,
        'airdate' => $airdate,
        'tvmaze_id' => $tvmazeId,
    ]);
    $episode->id = $id;

    return $episode;
}

it('returns an empty array when nothing is selected', function () {
    $result = EpisodeGrouping::groupBySeason(collect(), collect());

    expect($result)->toBe([]);
});

it('flags a full season when every regular is selected', function () {
    $regulars = collect([
        makeEpisode(1, 1, 1, '2024-01-01'),
        makeEpisode(2, 1, 2, '2024-01-08'),
        makeEpisode(3, 1, 3, '2024-01-15'),
    ]);

    $result = EpisodeGrouping::groupBySeason($regulars, $regulars);

    expect($result)->toHaveCount(1);
    expect($result[0]['season'])->toBe(1);
    expect($result[0]['is_full'])->toBeTrue();
    expect($result[0]['runs'])->toHaveCount(1);
    expect($result[0]['runs'][0])->toHaveCount(3);
});

it('finds a single run when consecutive episodes are selected', function () {
    $regulars = collect([
        makeEpisode(1, 1, 1, '2024-01-01'),
        makeEpisode(2, 1, 2, '2024-01-08'),
        makeEpisode(3, 1, 3, '2024-01-15'),
    ]);
    $selected = $regulars->take(2);

    $result = EpisodeGrouping::groupBySeason($selected, $regulars);

    expect($result[0]['is_full'])->toBeFalse();
    expect($result[0]['runs'])->toHaveCount(1);
    expect($result[0]['runs'][0]->pluck('id')->all())->toBe([1, 2]);
});

it('splits runs across a gap in airdate order', function () {
    $regulars = collect([
        makeEpisode(1, 1, 1, '2024-01-01'),
        makeEpisode(2, 1, 2, '2024-01-08'),
        makeEpisode(3, 1, 3, '2024-01-15'),
        makeEpisode(4, 1, 4, '2024-01-22'),
        makeEpisode(5, 1, 5, '2024-01-29'),
    ]);
    $selected = $regulars->whereIn('id', [1, 2, 4, 5])->values();

    $result = EpisodeGrouping::groupBySeason($selected, $regulars);

    $runs = $result[0]['runs'];
    expect($runs)->toHaveCount(2);
    expect($runs[0]->pluck('id')->all())->toBe([1, 2]);
    expect($runs[1]->pluck('id')->all())->toBe([4, 5]);
});

it('treats a season with no known regulars as not full', function () {
    $selected = collect([makeEpisode(10, 2, 1, '2024-02-01')]);

    $result = EpisodeGrouping::groupBySeason($selected, collect());

    expect($result)->toHaveCount(1);
    expect($result[0]['is_full'])->toBeFalse();
    expect($result[0]['runs'])->toBe([]);
});

it('drops selected episodes not present in the regulars list when finding runs', function () {
    $regulars = collect([
        makeEpisode(1, 1, 1, '2024-01-01'),
        makeEpisode(2, 1, 2, '2024-01-08'),
    ]);
    $orphan = makeEpisode(99, 1, 3, '2024-01-15');
    $selected = collect([$regulars[0], $orphan]);

    $result = EpisodeGrouping::groupBySeason($selected, $regulars);

    $runs = $result[0]['runs'];
    expect($runs)->toHaveCount(1);
    expect($runs[0]->pluck('id')->all())->toBe([1]);
    expect($result[0]['is_full'])->toBeFalse();
});

it('omits the selected episodes key by default', function () {
    $regulars = collect([makeEpisode(1, 1, 1, '2024-01-01')]);

    $result = EpisodeGrouping::groupBySeason($regulars, $regulars);

    expect($result[0])->not->toHaveKey('episodes');
});

it('includes the selected episodes key when requested', function () {
    $regulars = collect([makeEpisode(1, 1, 1, '2024-01-01')]);

    $result = EpisodeGrouping::groupBySeason($regulars, $regulars, includeSelectedCollection: true);

    expect($result[0])->toHaveKey('episodes');
    expect($result[0]['episodes'])->toBeInstanceOf(Collection::class);
    expect($result[0]['episodes']->pluck('id')->all())->toBe([1]);
});

it('orders multi-season output by season ascending', function () {
    $regulars = collect([
        makeEpisode(1, 1, 1, '2024-01-01'),
        makeEpisode(2, 2, 1, '2024-06-01'),
        makeEpisode(3, 3, 1, '2025-01-01'),
    ]);

    $result = EpisodeGrouping::groupBySeason($regulars, $regulars);

    expect(array_column($result, 'season'))->toBe([1, 2, 3]);
});
