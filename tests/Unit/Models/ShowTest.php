<?php

use App\Models\Episode;
use App\Models\Show;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('most_recent_season attribute', function () {
    it('returns currently airing season (has both past and future episodes)', function () {
        $show = Show::factory()->create();

        // Season 1 - all past episodes
        Episode::factory()->for($show)->create([
            'season' => 1,
            'airdate' => now()->subMonth(),
        ]);

        // Season 2 - currently airing (has past AND future episodes)
        Episode::factory()->for($show)->create([
            'season' => 2,
            'airdate' => now()->subWeek(),
        ]);
        Episode::factory()->for($show)->create([
            'season' => 2,
            'airdate' => now()->addWeek(),
        ]);

        expect($show->most_recent_season)->toBe(2);
    });

    it('returns highest completed season when no season is currently airing', function () {
        $show = Show::factory()->create();

        // Season 1 - all past
        Episode::factory()->for($show)->create([
            'season' => 1,
            'airdate' => now()->subMonth(),
        ]);

        // Season 2 - all past (most recently completed)
        Episode::factory()->for($show)->create([
            'season' => 2,
            'airdate' => now()->subWeek(),
        ]);

        // Season 3 - all future (hasn't started)
        Episode::factory()->for($show)->create([
            'season' => 3,
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
