<?php

use App\Enums\RequestItemStatus;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\Show;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('renders on the dashboard', function () {
    $this->get('/')
        ->assertSuccessful()
        ->assertSeeLivewire('dashboard.requests');
});

it('shows the empty state when the user has no requests', function () {
    Livewire::test('dashboard.requests')
        ->assertSuccessful()
        ->assertSee(__('lundbergh.empty.requests'));
});

it('shows a filter-specific empty state when filters match no requests', function () {
    $request = Request::factory()->for($this->user)->create();

    $pending = Movie::factory()->create(['title' => 'Pending Movie', 'year' => 2021]);
    RequestItem::factory()->forRequestable($pending)->pending()->create([
        'request_id' => $request->id,
    ]);

    Livewire::test('dashboard.requests')
        ->set('statusFilters', [RequestItemStatus::Rejected->value])
        ->assertSee(__('lundbergh.dashboard.no_matching_requests'))
        ->assertDontSee(__('lundbergh.empty.requests'));
});

it('renders a movie row with title and year', function () {
    $movie = Movie::factory()->create(['title' => 'Inception', 'year' => 2010]);
    $request = Request::factory()->for($this->user)->create();
    RequestItem::factory()->forRequestable($movie)->fulfilled($this->user->id)->create([
        'request_id' => $request->id,
    ]);

    Livewire::test('dashboard.requests')
        ->assertSuccessful()
        ->assertSee('Inception (2010)')
        ->assertSee('Fulfilled');
});

it('renders a single episode row with show name and episode code', function () {
    $show = Show::factory()->create(['name' => 'Lost']);
    $episode = Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 5,
        'airdate' => '2004-10-27',
    ]);
    $request = Request::factory()->for($this->user)->create();
    RequestItem::factory()->forRequestable($episode)->pending()->create([
        'request_id' => $request->id,
    ]);

    Livewire::test('dashboard.requests')
        ->assertSuccessful()
        ->assertSee('Lost')
        ->assertSee('S01E05')
        ->assertSee('Pending');
});

it('consolidates contiguous episodes with the same status', function () {
    $show = Show::factory()->create(['name' => 'Fargo']);

    // Create full season (E01-E10) so full-season detection doesn't trigger
    $episodes = collect();
    for ($i = 1; $i <= 10; $i++) {
        $episodes->put($i, Episode::factory()->create([
            'show_id' => $show->id,
            'season' => 1,
            'number' => $i,
            'airdate' => '2014-04-'.str_pad($i, 2, '0', STR_PAD_LEFT),
        ]));
    }

    // Only request E02-E04
    $request = Request::factory()->for($this->user)->create();
    foreach ([2, 3, 4] as $num) {
        RequestItem::factory()->forRequestable($episodes[$num])->fulfilled($this->user->id)->create([
            'request_id' => $request->id,
        ]);
    }

    Livewire::test('dashboard.requests')
        ->assertSuccessful()
        ->assertSee('S01E02-E04')
        ->assertDontSee('S01E03');
});

it('splits runs on status change', function () {
    $show = Show::factory()->create(['name' => 'Dexter']);
    $request = Request::factory()->for($this->user)->create();

    // E01-E02 Fulfilled
    for ($i = 1; $i <= 2; $i++) {
        $ep = Episode::factory()->create([
            'show_id' => $show->id,
            'season' => 1,
            'number' => $i,
            'airdate' => "2006-10-0{$i}",
        ]);
        RequestItem::factory()->forRequestable($ep)->fulfilled($this->user->id)->create([
            'request_id' => $request->id,
        ]);
    }

    // E03 Rejected
    $ep3 = Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 3,
        'airdate' => '2006-10-03',
    ]);
    RequestItem::factory()->forRequestable($ep3)->rejected($this->user->id)->create([
        'request_id' => $request->id,
    ]);

    Livewire::test('dashboard.requests')
        ->assertSuccessful()
        ->assertSee('S01E01-E02')
        ->assertSee('S01E03')
        ->assertSee('Fulfilled')
        ->assertSee('Rejected');
});

it('splits runs on non-contiguity', function () {
    $show = Show::factory()->create(['name' => 'Suits']);
    $request = Request::factory()->for($this->user)->create();

    // E01-E02 Fulfilled (contiguous)
    for ($i = 1; $i <= 2; $i++) {
        $ep = Episode::factory()->create([
            'show_id' => $show->id,
            'season' => 1,
            'number' => $i,
            'airdate' => "2011-06-2{$i}",
        ]);
        RequestItem::factory()->forRequestable($ep)->fulfilled($this->user->id)->create([
            'request_id' => $request->id,
        ]);
    }

    // E05-E06 Fulfilled (gap at 3-4)
    for ($i = 5; $i <= 6; $i++) {
        $ep = Episode::factory()->create([
            'show_id' => $show->id,
            'season' => 1,
            'number' => $i,
            'airdate' => "2011-07-0{$i}",
        ]);
        RequestItem::factory()->forRequestable($ep)->fulfilled($this->user->id)->create([
            'request_id' => $request->id,
        ]);
    }

    Livewire::test('dashboard.requests')
        ->assertSuccessful()
        ->assertSee('S01E01-E02')
        ->assertSee('S01E05-E06');
});

it('displays full season label when all episodes present with same status', function () {
    $show = Show::factory()->create(['name' => 'Fleabag']);
    $request = Request::factory()->for($this->user)->create();

    // Create a 6-episode season and request all of them
    for ($i = 1; $i <= 6; $i++) {
        $ep = Episode::factory()->create([
            'show_id' => $show->id,
            'season' => 1,
            'number' => $i,
            'airdate' => "2016-07-{$i}",
        ]);
        RequestItem::factory()->forRequestable($ep)->fulfilled($this->user->id)->create([
            'request_id' => $request->id,
        ]);
    }

    Livewire::test('dashboard.requests')
        ->assertSuccessful()
        ->assertSee('Fleabag')
        ->assertSee('S01')
        ->assertDontSee('S01E01');
});

it('sorts by created_at descending', function () {
    $movieOld = Movie::factory()->create(['title' => 'Old Movie', 'year' => 2000]);
    $movieNew = Movie::factory()->create(['title' => 'New Movie', 'year' => 2024]);

    $requestOld = Request::factory()->for($this->user)->create(['created_at' => now()->subWeek()]);
    $requestNew = Request::factory()->for($this->user)->create(['created_at' => now()]);

    RequestItem::factory()->forRequestable($movieOld)->pending()->create([
        'request_id' => $requestOld->id,
        'created_at' => now()->subWeek(),
    ]);
    RequestItem::factory()->forRequestable($movieNew)->pending()->create([
        'request_id' => $requestNew->id,
        'created_at' => now(),
    ]);

    Livewire::test('dashboard.requests')
        ->assertSuccessful()
        ->assertSeeInOrder(['New Movie', 'Old Movie']);
});

it('paginates with more than 5 rows', function () {
    $request = Request::factory()->for($this->user)->create();

    // Create 7 movies
    for ($i = 1; $i <= 7; $i++) {
        $movie = Movie::factory()->create(['title' => "Movie $i", 'year' => 2020 + $i]);
        RequestItem::factory()->forRequestable($movie)->pending()->create([
            'request_id' => $request->id,
        ]);
    }

    Livewire::test('dashboard.requests')
        ->assertSuccessful()
        ->call('nextPage')
        ->assertSuccessful();
});

it('filters by a single status', function () {
    $request = Request::factory()->for($this->user)->create();

    $fulfilled = Movie::factory()->create(['title' => 'Fulfilled Movie', 'year' => 2020]);
    RequestItem::factory()->forRequestable($fulfilled)->fulfilled($this->user->id)->create([
        'request_id' => $request->id,
    ]);

    $pending = Movie::factory()->create(['title' => 'Pending Movie', 'year' => 2021]);
    RequestItem::factory()->forRequestable($pending)->pending()->create([
        'request_id' => $request->id,
    ]);

    Livewire::test('dashboard.requests')
        ->set('statusFilters', [RequestItemStatus::Fulfilled->value])
        ->assertSee('Fulfilled Movie')
        ->assertDontSee('Pending Movie');
});

it('filters by multiple statuses', function () {
    $request = Request::factory()->for($this->user)->create();

    $fulfilled = Movie::factory()->create(['title' => 'Fulfilled Movie', 'year' => 2020]);
    RequestItem::factory()->forRequestable($fulfilled)->fulfilled($this->user->id)->create([
        'request_id' => $request->id,
    ]);

    $rejected = Movie::factory()->create(['title' => 'Rejected Movie', 'year' => 2021]);
    RequestItem::factory()->forRequestable($rejected)->rejected($this->user->id)->create([
        'request_id' => $request->id,
    ]);

    $pending = Movie::factory()->create(['title' => 'Pending Movie', 'year' => 2022]);
    RequestItem::factory()->forRequestable($pending)->pending()->create([
        'request_id' => $request->id,
    ]);

    Livewire::test('dashboard.requests')
        ->set('statusFilters', [RequestItemStatus::Fulfilled->value, RequestItemStatus::Rejected->value])
        ->assertSee('Fulfilled Movie')
        ->assertSee('Rejected Movie')
        ->assertDontSee('Pending Movie');
});

it('shows all rows when no filters are selected', function () {
    $request = Request::factory()->for($this->user)->create();

    $fulfilled = Movie::factory()->create(['title' => 'Fulfilled Movie', 'year' => 2020]);
    RequestItem::factory()->forRequestable($fulfilled)->fulfilled($this->user->id)->create([
        'request_id' => $request->id,
    ]);

    $pending = Movie::factory()->create(['title' => 'Pending Movie', 'year' => 2021]);
    RequestItem::factory()->forRequestable($pending)->pending()->create([
        'request_id' => $request->id,
    ]);

    Livewire::test('dashboard.requests')
        ->set('statusFilters', [])
        ->assertSee('Fulfilled Movie')
        ->assertSee('Pending Movie');
});

it('shows a filter-specific empty state when no rows match the selected filters', function () {
    $request = Request::factory()->for($this->user)->create();

    $pending = Movie::factory()->create(['title' => 'Pending Movie', 'year' => 2021]);
    RequestItem::factory()->forRequestable($pending)->pending()->create([
        'request_id' => $request->id,
    ]);

    Livewire::test('dashboard.requests')
        ->set('statusFilters', [RequestItemStatus::Rejected->value])
        ->assertSee(__('lundbergh.dashboard.no_matching_requests'))
        ->assertDontSee(__('lundbergh.empty.requests'));
});

it('resets pagination when filters change', function () {
    $request = Request::factory()->for($this->user)->create();

    for ($i = 1; $i <= 7; $i++) {
        $movie = Movie::factory()->create(['title' => "Movie $i", 'year' => 2020 + $i]);
        RequestItem::factory()->forRequestable($movie)->pending()->create([
            'request_id' => $request->id,
        ]);
    }

    Livewire::test('dashboard.requests')
        ->call('nextPage')
        ->set('statusFilters', [RequestItemStatus::Pending->value])
        ->assertSet('paginators.page', 1);
});
