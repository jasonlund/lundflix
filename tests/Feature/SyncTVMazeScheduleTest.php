<?php

use App\Enums\EpisodeType;
use App\Models\Episode;
use App\Models\Show;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

it('syncs episodes only for shows that have existing episodes', function () {
    // Show with episodes (should be synced)
    $trackedShow = Show::factory()->create(['tvmaze_id' => 100]);
    Episode::factory()->create([
        'show_id' => $trackedShow->id,
        'tvmaze_id' => 1000,
        'season' => 1,
        'number' => 1,
    ]);

    // Show without episodes (should be ignored)
    Show::factory()->create(['tvmaze_id' => 200]);

    Http::fake([
        'api.tvmaze.com/schedule/full' => Http::response([
            [
                'id' => 2001,
                'name' => 'New Episode',
                'season' => 1,
                'number' => 2,
                'airdate' => now()->addDays(3)->format('Y-m-d'),
                'airtime' => '21:00',
                'runtime' => 60,
                'type' => 'regular',
                'rating' => ['average' => null],
                'image' => null,
                'summary' => null,
                '_embedded' => ['show' => ['id' => 100, 'name' => 'Tracked Show']],
            ],
            [
                'id' => 2002,
                'name' => 'Ignored Episode',
                'season' => 1,
                'number' => 1,
                'airdate' => now()->addDays(3)->format('Y-m-d'),
                'airtime' => '20:00',
                'runtime' => 30,
                'type' => 'regular',
                'rating' => ['average' => null],
                'image' => null,
                'summary' => null,
                '_embedded' => ['show' => ['id' => 200, 'name' => 'Untracked Show']],
            ],
        ]),
    ]);

    $this->artisan('tvmaze:sync-schedule')
        ->assertSuccessful();

    // Tracked show should have new episode
    expect(Episode::where('tvmaze_id', 2001)->exists())->toBeTrue();

    // Untracked show should NOT have episode
    expect(Episode::where('tvmaze_id', 2002)->exists())->toBeFalse();
});

it('updates existing episodes with new data', function () {
    $show = Show::factory()->create(['tvmaze_id' => 100]);

    // Existing episode with placeholder name
    Episode::factory()->create([
        'show_id' => $show->id,
        'tvmaze_id' => 5000,
        'name' => 'TBA',
        'season' => 2,
        'number' => 1,
        'airdate' => now()->addDays(3),
        'summary' => null,
    ]);

    Http::fake([
        'api.tvmaze.com/schedule/full' => Http::response([
            [
                'id' => 5000,
                'name' => 'The Real Episode Title',
                'season' => 2,
                'number' => 1,
                'airdate' => now()->addDays(3)->format('Y-m-d'),
                'airtime' => '21:00',
                'runtime' => 60,
                'type' => 'regular',
                'rating' => ['average' => 8.5],
                'image' => ['medium' => 'https://example.com/img.jpg'],
                'summary' => '<p>Now with a summary!</p>',
                '_embedded' => ['show' => ['id' => 100, 'name' => 'Test Show']],
            ],
        ]),
    ]);

    $this->artisan('tvmaze:sync-schedule')
        ->assertSuccessful();

    $episode = Episode::where('tvmaze_id', 5000)->first();

    expect($episode->name)->toBe('The Real Episode Title')
        ->and($episode->summary)->toBe('<p>Now with a summary!</p>');
});

it('handles empty schedule gracefully', function () {
    $show = Show::factory()->create(['tvmaze_id' => 100]);
    Episode::factory()->create(['show_id' => $show->id]);

    Http::fake([
        'api.tvmaze.com/schedule/full' => Http::response([]),
    ]);

    $this->artisan('tvmaze:sync-schedule')
        ->assertSuccessful()
        ->expectsOutputToContain('No relevant episodes found in schedule.');
});

it('skips API call when no shows have episodes', function () {
    Show::factory()->create(['tvmaze_id' => 100]); // No episodes

    $this->artisan('tvmaze:sync-schedule')
        ->assertSuccessful()
        ->expectsOutputToContain('No shows with episodes to sync.');

    Http::assertNothingSent();
});

it('throws exception on API failure', function () {
    $show = Show::factory()->create(['tvmaze_id' => 100]);
    Episode::factory()->create(['show_id' => $show->id]);

    Http::fake([
        'api.tvmaze.com/schedule/full' => Http::response([], 500),
    ]);

    $this->artisan('tvmaze:sync-schedule');
})->throws(\Illuminate\Http\Client\RequestException::class);

it('syncs multiple episodes for the same show', function () {
    $show = Show::factory()->create(['tvmaze_id' => 100]);
    Episode::factory()->create([
        'show_id' => $show->id,
        'tvmaze_id' => 1000,
        'season' => 1,
        'number' => 1,
    ]);

    Http::fake([
        'api.tvmaze.com/schedule/full' => Http::response([
            [
                'id' => 1001,
                'name' => 'Episode 2',
                'season' => 1,
                'number' => 2,
                'airdate' => now()->addDays(7)->format('Y-m-d'),
                'airtime' => '21:00',
                'runtime' => 60,
                'type' => 'regular',
                'rating' => ['average' => null],
                'image' => null,
                'summary' => null,
                '_embedded' => ['show' => ['id' => 100]],
            ],
            [
                'id' => 1002,
                'name' => 'Episode 3',
                'season' => 1,
                'number' => 3,
                'airdate' => now()->addDays(14)->format('Y-m-d'),
                'airtime' => '21:00',
                'runtime' => 60,
                'type' => 'regular',
                'rating' => ['average' => null],
                'image' => null,
                'summary' => null,
                '_embedded' => ['show' => ['id' => 100]],
            ],
        ]),
    ]);

    $this->artisan('tvmaze:sync-schedule')
        ->assertSuccessful();

    expect(Episode::where('tvmaze_id', 1001)->exists())->toBeTrue()
        ->and(Episode::where('tvmaze_id', 1002)->exists())->toBeTrue()
        ->and($show->episodes()->count())->toBe(3);
});

it('syncs episodes for multiple tracked shows', function () {
    $show1 = Show::factory()->create(['tvmaze_id' => 100]);
    Episode::factory()->create(['show_id' => $show1->id, 'tvmaze_id' => 1000, 'season' => 1, 'number' => 1]);

    $show2 = Show::factory()->create(['tvmaze_id' => 200]);
    Episode::factory()->create(['show_id' => $show2->id, 'tvmaze_id' => 2000, 'season' => 1, 'number' => 1]);

    Http::fake([
        'api.tvmaze.com/schedule/full' => Http::response([
            [
                'id' => 1001,
                'name' => 'Show 1 Episode',
                'season' => 1,
                'number' => 2,
                'airdate' => now()->addDays(3)->format('Y-m-d'),
                'airtime' => '21:00',
                'runtime' => 60,
                'type' => 'regular',
                'rating' => ['average' => null],
                'image' => null,
                'summary' => null,
                '_embedded' => ['show' => ['id' => 100]],
            ],
            [
                'id' => 2001,
                'name' => 'Show 2 Episode',
                'season' => 1,
                'number' => 2,
                'airdate' => now()->addDays(4)->format('Y-m-d'),
                'airtime' => '20:00',
                'runtime' => 30,
                'type' => 'regular',
                'rating' => ['average' => null],
                'image' => null,
                'summary' => null,
                '_embedded' => ['show' => ['id' => 200]],
            ],
        ]),
    ]);

    $this->artisan('tvmaze:sync-schedule')
        ->assertSuccessful();

    expect(Episode::where('tvmaze_id', 1001)->first()->show_id)->toBe($show1->id)
        ->and(Episode::where('tvmaze_id', 2001)->first()->show_id)->toBe($show2->id);
});

it('handles special episodes correctly', function () {
    $show = Show::factory()->create(['tvmaze_id' => 100]);
    Episode::factory()->create(['show_id' => $show->id, 'tvmaze_id' => 1000, 'season' => 1, 'number' => 1]);

    Http::fake([
        'api.tvmaze.com/schedule/full' => Http::response([
            [
                'id' => 1001,
                'name' => 'Holiday Special',
                'season' => 1,
                'number' => null,
                'airdate' => now()->addDays(10)->format('Y-m-d'),
                'airtime' => '21:00',
                'runtime' => 90,
                'type' => 'significant_special',
                'rating' => ['average' => null],
                'image' => null,
                'summary' => null,
                '_embedded' => ['show' => ['id' => 100]],
            ],
        ]),
    ]);

    $this->artisan('tvmaze:sync-schedule')
        ->assertSuccessful();

    $special = Episode::where('tvmaze_id', 1001)->first();

    expect($special)->not->toBeNull()
        ->and($special->type)->toBe(EpisodeType::SignificantSpecial)
        ->and($special->number)->toBe(1); // Assigned by UpsertEpisodes
});
