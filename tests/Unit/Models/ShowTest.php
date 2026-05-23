<?php

use App\Enums\ArtworkType;
use App\Models\Episode;
use App\Models\Media;
use App\Models\Show;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::forget(Show::AMBIGUOUS_NAMES_CACHE_KEY);
});

describe('most_recent_season attribute', function () {
    it('returns currently airing season (has both past and future episodes)', function () {
        $show = Show::factory()->create();

        // Season 1 - all past episodes
        Episode::factory()->for($show)->create([
            'season' => 1,
            'number' => 1,
            'airdate' => now()->subMonth(),
        ]);

        // Season 2 - currently airing (has past AND future episodes)
        Episode::factory()->for($show)->create([
            'season' => 2,
            'number' => 1,
            'airdate' => now()->subWeek(),
        ]);
        Episode::factory()->for($show)->create([
            'season' => 2,
            'number' => 2,
            'airdate' => now()->addWeek(),
        ]);

        expect($show->most_recent_season)->toBe(2);
    });

    it('returns highest completed season when no season is currently airing', function () {
        $show = Show::factory()->create();

        // Season 1 - all past
        Episode::factory()->for($show)->create([
            'season' => 1,
            'number' => 1,
            'airdate' => now()->subMonth(),
        ]);

        // Season 2 - all past (most recently completed)
        Episode::factory()->for($show)->create([
            'season' => 2,
            'number' => 1,
            'airdate' => now()->subWeek(),
        ]);

        // Season 3 - all future (hasn't started)
        Episode::factory()->for($show)->create([
            'season' => 3,
            'number' => 1,
            'airdate' => now()->addMonth(),
        ]);

        expect($show->most_recent_season)->toBe(2);
    });

    it('returns null when show has no episodes', function () {
        $show = Show::factory()->create();

        expect($show->most_recent_season)->toBeNull();
    });

    it('returns highest currently airing season when multiple seasons are airing', function () {
        $show = Show::factory()->create();

        // Season 1 - currently airing
        Episode::factory()->for($show)->create([
            'season' => 1,
            'number' => 1,
            'airdate' => now()->subWeek(),
        ]);
        Episode::factory()->for($show)->create([
            'season' => 1,
            'number' => 2,
            'airdate' => now()->addWeek(),
        ]);

        // Season 2 - also currently airing
        Episode::factory()->for($show)->create([
            'season' => 2,
            'number' => 1,
            'airdate' => now()->subDay(),
        ]);
        Episode::factory()->for($show)->create([
            'season' => 2,
            'number' => 2,
            'airdate' => now()->addMonth(),
        ]);

        expect($show->most_recent_season)->toBe(2);
    });

    it('returns first season when all episodes are in the future (upcoming show)', function () {
        $show = Show::factory()->create();

        // All episodes in the future - show hasn't premiered yet
        Episode::factory()->for($show)->create([
            'season' => 1,
            'number' => 1,
            'airdate' => now()->addMonth(),
        ]);
        Episode::factory()->for($show)->create([
            'season' => 1,
            'number' => 2,
            'airdate' => now()->addMonths(2),
        ]);

        expect($show->most_recent_season)->toBe(1);
    });
});

describe('art helpers', function () {
    it('returns null art url when missing tmdb id', function () {
        $show = Show::factory()->create(['tmdb_id' => null]);

        expect($show->artUrl('background'))->toBeNull();
    });

    it('builds an art url when tmdb id is present and active media exists', function () {
        $show = Show::factory()->create(['tmdb_id' => 12345]);

        Media::factory()->active()->create([
            'mediable_type' => Show::class,
            'mediable_id' => $show->id,
            'type' => ArtworkType::Logo->value,
        ]);

        expect($show->artUrl('logo'))
            ->toBe(route('art', ['mediable' => 'show', 'id' => $show->sqid, 'type' => 'logo']));
    });

    it('reports whether art can be fetched', function () {
        $showWithTmdbId = Show::factory()->create(['tmdb_id' => 12345]);
        $showWithoutTmdbId = Show::factory()->create(['tmdb_id' => null]);

        expect($showWithTmdbId->canHaveArt())->toBeTrue()
            ->and($showWithoutTmdbId->canHaveArt())->toBeFalse();
    });
});

describe('name accessor', function () {
    $network = function (string $code): array {
        return ['id' => 1, 'name' => 'Net', 'country' => ['name' => 'X', 'code' => $code, 'timezone' => 'UTC']];
    };

    it('returns raw name when no collision exists', function () use ($network) {
        Show::factory()->create(['name' => 'Solo Show', 'network' => $network('US')]);
        Show::recomputeAmbiguousNames();

        $show = Show::firstWhere('name', 'Solo Show');
        expect($show->name)->toBe('Solo Show');
    });

    it('appends country code when the base name collides across countries', function () use ($network) {
        Show::factory()->create(['name' => 'Taskmaster', 'network' => $network('GB')]);
        Show::factory()->create(['name' => 'Taskmaster', 'network' => $network('AU')]);
        Show::recomputeAmbiguousNames();

        $uk = Show::where('network->country->code', 'GB')->first();
        $au = Show::where('network->country->code', 'AU')->first();

        expect($uk->name)->toBe('Taskmaster UK')
            ->and($au->name)->toBe('Taskmaster AU');
    });

    it('does not double up when the name already contains the code', function () use ($network) {
        Show::factory()->create(['name' => 'Taskmaster', 'network' => $network('GB')]);
        Show::factory()->create(['name' => 'Taskmaster NZ', 'network' => $network('NZ')]);
        Show::recomputeAmbiguousNames();

        $nz = Show::where('network->country->code', 'NZ')->first();
        expect($nz->name)->toBe('Taskmaster NZ');
    });

    it('falls back to web_channel country code when network is null', function () {
        Show::factory()->create([
            'name' => 'Streamer',
            'network' => null,
            'web_channel' => ['id' => 310, 'name' => 'Apple TV+', 'country' => ['code' => 'US']],
        ]);
        Show::factory()->create(['name' => 'Streamer', 'network' => ['id' => 1, 'name' => 'Net', 'country' => ['code' => 'CA']]]);
        Show::recomputeAmbiguousNames();

        $us = Show::where('web_channel->country->code', 'US')->first();
        expect($us->name)->toBe('Streamer US');
    });

    it('returns raw name when no country code is available', function () {
        Show::factory()->create(['name' => 'Foo', 'network' => null, 'web_channel' => null]);
        Show::factory()->create(['name' => 'Foo', 'network' => null, 'web_channel' => null]);
        Show::recomputeAmbiguousNames();

        $shows = Show::where('name', 'Foo')->get();
        foreach ($shows as $show) {
            expect($show->name)->toBe('Foo');
        }
    });

    it('exposes the raw column via getRawOriginal', function () use ($network) {
        Show::factory()->create(['name' => 'Taskmaster', 'network' => $network('GB')]);
        Show::factory()->create(['name' => 'Taskmaster', 'network' => $network('AU')]);
        Show::recomputeAmbiguousNames();

        $uk = Show::where('network->country->code', 'GB')->first();
        expect($uk->name)->toBe('Taskmaster UK')
            ->and($uk->getRawOriginal('name'))->toBe('Taskmaster');
    });

    it('recomputes the cache when sync explicitly refreshes it', function () use ($network) {
        Show::factory()->create(['name' => 'Survivor', 'network' => $network('US')]);
        Show::recomputeAmbiguousNames();

        $us = Show::where('network->country->code', 'US')->first();
        expect($us->fresh()->name)->toBe('Survivor');

        Show::factory()->create(['name' => 'Survivor', 'network' => $network('AU')]);
        Show::recomputeAmbiguousNames();

        expect($us->fresh()->name)->toBe('Survivor US');
    });
});
