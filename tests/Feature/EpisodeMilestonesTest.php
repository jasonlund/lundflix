<?php

use App\Models\Episode;
use App\Models\Show;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    Http::preventStrayRequests();

    config([
        'services.plex.client_identifier' => 'test-client-id',
        'services.plex.product_name' => 'Lund',
        'services.plex.server_identifier' => 'test-server-123',
    ]);
});

describe('show availability episode milestones', function () {
    it('shows pilot, last aired, and next to air milestones', function () {
        $user = User::factory()->create();
        $show = Show::factory()->create([
            'status' => 'Running',
            'network' => ['id' => 1, 'name' => 'NBC', 'country' => ['name' => 'United States', 'timezone' => 'America/New_York']],
        ]);

        Episode::factory()->create([
            'show_id' => $show->id,
            'season' => 1,
            'number' => 1,
            'name' => 'Pilot Episode',
            'airdate' => '2020-01-01',
            'airtime' => '21:00',
            'runtime' => 60,
        ]);

        Episode::factory()->create([
            'show_id' => $show->id,
            'season' => 3,
            'number' => 5,
            'name' => 'Recent One',
            'airdate' => now()->subDays(3)->format('Y-m-d'),
            'airtime' => '21:00',
            'runtime' => 45,
        ]);

        Episode::factory()->create([
            'show_id' => $show->id,
            'season' => 3,
            'number' => 6,
            'name' => 'Upcoming One',
            'airdate' => now()->addWeek()->format('Y-m-d'),
            'airtime' => '21:00',
            'runtime' => 45,
        ]);

        $show->load('episodes');

        Livewire::actingAs($user)
            ->test('shows.availability', ['show' => $show])
            ->assertSee('Pilot Episode')
            ->assertSee('Recent One')
            ->assertSee('Upcoming One')
            ->assertSee('S01E01')
            ->assertSee('S03E05')
            ->assertSee('S03E06');
    });

    it('shows only pilot when show has a single episode', function () {
        $user = User::factory()->create();
        $show = Show::factory()->create([
            'status' => 'Running',
            'network' => ['id' => 1, 'name' => 'NBC', 'country' => ['name' => 'United States', 'timezone' => 'America/New_York']],
        ]);

        Episode::factory()->create([
            'show_id' => $show->id,
            'season' => 1,
            'number' => 1,
            'name' => 'Only Episode',
            'airdate' => '2020-01-01',
            'airtime' => '21:00',
            'runtime' => 60,
        ]);

        $show->load('episodes');

        Livewire::actingAs($user)
            ->test('shows.availability', ['show' => $show])
            ->assertSee('Pilot')
            ->assertSee('Only Episode')
            ->assertDontSee('Last Aired');
    });

    it('handles empty episodes gracefully', function () {
        $user = User::factory()->create();
        $show = Show::factory()->create([
            'status' => 'In Development',
        ]);

        $show->load('episodes');

        Livewire::actingAs($user)
            ->test('shows.availability', ['show' => $show])
            ->assertDontSee('Pilot')
            ->assertDontSee('Last Aired')
            ->assertDontSee('Next to Air');
    });

    it('displays the show status badge', function () {
        $user = User::factory()->create();
        $show = Show::factory()->create([
            'status' => 'Running',
        ]);

        $show->load('episodes');

        Livewire::actingAs($user)
            ->test('shows.availability', ['show' => $show])
            ->assertSee('Running');
    });

    it('orders same-day milestones by resolved airtime', function () {
        $this->travelTo(Carbon::create(2026, 4, 20, 3, 0, 0, 'UTC'));

        $user = User::factory()->create();
        $show = Show::factory()->create([
            'status' => 'Running',
            'network' => ['id' => 1, 'name' => 'NBC', 'country' => ['name' => 'United States', 'timezone' => 'America/New_York']],
        ]);

        Episode::factory()->create([
            'show_id' => $show->id,
            'season' => 1,
            'number' => 1,
            'name' => 'Pilot Episode',
            'airdate' => '2020-01-01',
            'airtime' => '21:00',
            'runtime' => 60,
        ]);

        Episode::factory()->create([
            'show_id' => $show->id,
            'season' => 2,
            'number' => 1,
            'name' => 'Past Earlier',
            'airdate' => '2026-04-19',
            'airtime' => '20:00',
            'runtime' => 45,
        ]);

        Episode::factory()->create([
            'show_id' => $show->id,
            'season' => 2,
            'number' => 2,
            'name' => 'Past Later',
            'airdate' => '2026-04-19',
            'airtime' => '22:00',
            'runtime' => 45,
        ]);

        Episode::factory()->create([
            'show_id' => $show->id,
            'season' => 2,
            'number' => 4,
            'name' => 'Future Later',
            'airdate' => '2026-04-20',
            'airtime' => '22:00',
            'runtime' => 45,
        ]);

        Episode::factory()->create([
            'show_id' => $show->id,
            'season' => 2,
            'number' => 3,
            'name' => 'Future Earlier',
            'airdate' => '2026-04-20',
            'airtime' => '20:00',
            'runtime' => 45,
        ]);

        $show->load('episodes');

        $milestones = Livewire::actingAs($user)
            ->test('shows.availability', ['show' => $show])
            ->instance()->episodeMilestones;

        expect($milestones['last_aired']['name'])->toBe('Past Later')
            ->and($milestones['next_to_air']['name'])->toBe('Future Earlier');
    });
});
