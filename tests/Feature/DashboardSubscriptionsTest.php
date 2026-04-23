<?php

use App\Models\Episode;
use App\Models\Movie;
use App\Models\Show;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders subscriptions when they exist', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create(['title' => 'Test Movie', 'year' => 2024]);
    Subscription::factory()->forSubscribable($movie)->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get('/')
        ->assertSuccessful()
        ->assertSee('Test Movie (2024)');
});

it('is hidden when user has no subscriptions', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create(['title' => 'Some Movie', 'year' => 2024]);

    $this->actingAs($user)
        ->get('/')
        ->assertSuccessful()
        ->assertDontSee('Some Movie (2024)');
});

it('shows relative time for movie digital release date', function () {
    $this->travelTo(now()->startOfDay()->addHours(12));

    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'title' => 'Upcoming Movie',
        'year' => 2026,
        'digital_release_date' => today()->addDays(10),
    ]);
    Subscription::factory()->forSubscribable($movie)->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)->test('dashboard.subscriptions');
    $rows = $component->get('allRows');

    expect($rows->first()['detail'])->toBe('1w');
});

it('shows Unknown for movie with no digital release date', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'title' => 'No Date Movie',
        'year' => 2024,
        'digital_release_date' => null,
    ]);
    Subscription::factory()->forSubscribable($movie)->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)->test('dashboard.subscriptions');
    $rows = $component->get('allRows');

    expect($rows->first()['detail'])->toBe('Unknown');
});

it('shows relative time for show next episode', function () {
    $this->travelTo(now()->startOfDay()->addHours(12));

    $user = User::factory()->create();
    $show = Show::factory()->create(['name' => 'Cool Show']);
    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 5,
        'airdate' => today()->addDays(4),
        'airtime' => null,
    ]);
    Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)->test('dashboard.subscriptions');
    $rows = $component->get('allRows');

    expect($rows->first()['detail'])->toBe('3d');
});

it('shows Unknown when show has no upcoming episodes', function () {
    $user = User::factory()->create();
    $show = Show::factory()->create(['name' => 'Old Show']);
    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 1,
        'airdate' => now()->subDays(30),
    ]);
    Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)->test('dashboard.subscriptions');
    $rows = $component->get('allRows');

    expect($rows->first()['detail'])->toBe('Unknown');
});

it('shows episode run as subtitle for single episode', function () {
    $user = User::factory()->create();
    $show = Show::factory()->create(['name' => 'Episode Show']);
    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 2,
        'number' => 3,
        'airdate' => now()->addDays(1),
        'airtime' => null,
    ]);
    Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('dashboard.subscriptions')
        ->assertSee('S02E03');
});

it('groups episodes airing on the same date', function () {
    $user = User::factory()->create();
    $show = Show::factory()->create(['name' => 'Double Show']);
    $airdate = now()->addDays(2);

    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 1,
        'airdate' => $airdate,
        'airtime' => null,
    ]);
    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 2,
        'airdate' => $airdate,
        'airtime' => null,
    ]);
    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 3,
        'airdate' => now()->addDays(9),
        'airtime' => null,
    ]);

    Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('dashboard.subscriptions')
        ->assertSee('S01E01-E02');
});

it('sorts subscriptions by upcoming date ascending with nulls last', function () {
    $this->travelTo(now()->startOfDay()->addHours(12));

    $user = User::factory()->create();

    $laterMovie = Movie::factory()->create([
        'title' => 'Later Movie',
        'year' => 2026,
        'digital_release_date' => today()->addMonths(2),
    ]);
    $soonerMovie = Movie::factory()->create([
        'title' => 'Sooner Movie',
        'year' => 2026,
        'digital_release_date' => today()->addDays(5),
    ]);
    $noDateMovie = Movie::factory()->create([
        'title' => 'No Date Movie',
        'year' => 2026,
        'digital_release_date' => null,
    ]);

    Subscription::factory()->forSubscribable($laterMovie)->create(['user_id' => $user->id]);
    Subscription::factory()->forSubscribable($soonerMovie)->create(['user_id' => $user->id]);
    Subscription::factory()->forSubscribable($noDateMovie)->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)->test('dashboard.subscriptions');
    $rows = $component->get('allRows');

    expect($rows[0]['title'])->toBe('Sooner Movie (2026)');
    expect($rows[1]['title'])->toBe('Later Movie (2026)');
    expect($rows[2]['title'])->toBe('No Date Movie (2026)');
});

it('displays the Subscriptions header', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create(['title' => 'Header Movie', 'year' => 2024]);
    Subscription::factory()->forSubscribable($movie)->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('dashboard.subscriptions')
        ->assertSee('Subscriptions');
});

it('defaults to upcoming view', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create(['title' => 'Some Movie', 'year' => 2026]);
    Subscription::factory()->forSubscribable($movie)->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)->test('dashboard.subscriptions');

    expect($component->get('view'))->toBe('upcoming');
});

it('shows recently released movies in recent view', function () {
    $this->travelTo(now()->startOfDay()->addHours(12));

    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'title' => 'Released Movie',
        'year' => 2026,
        'digital_release_date' => today()->subDays(5),
    ]);
    Subscription::factory()->forSubscribable($movie)->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)->test('dashboard.subscriptions');
    $component->set('view', 'recent');
    $rows = $component->get('allRows');

    expect($rows->first()['title'])->toBe('Released Movie (2026)')
        ->and($rows->first()['detail'])->toBe('5d');
});

it('falls back to release_date for recent movies without digital_release_date', function () {
    $this->travelTo(now()->startOfDay()->addHours(12));

    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'title' => 'Theater Movie',
        'year' => 2026,
        'digital_release_date' => null,
        'release_date' => today()->subDays(14),
    ]);
    Subscription::factory()->forSubscribable($movie)->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)->test('dashboard.subscriptions');
    $component->set('view', 'recent');
    $rows = $component->get('allRows');

    expect($rows->first()['title'])->toBe('Theater Movie (2026)')
        ->and($rows->first()['detail'])->toBe('2w');
});

it('excludes movies with no past release date from recent view', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'title' => 'Future Movie',
        'year' => 2026,
        'digital_release_date' => today()->addDays(30),
        'release_date' => today()->addDays(10),
    ]);
    Subscription::factory()->forSubscribable($movie)->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)->test('dashboard.subscriptions');
    $component->set('view', 'recent');
    $rows = $component->get('allRows');

    expect($rows)->toBeEmpty();
});

it('shows most recently aired episode in recent view', function () {
    $this->travelTo(now()->startOfDay()->addHours(12));

    $user = User::factory()->create();
    $show = Show::factory()->create(['name' => 'Aired Show']);
    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 2,
        'number' => 5,
        'airdate' => today()->subDays(3),
        'airtime' => null,
    ]);
    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 2,
        'number' => 4,
        'airdate' => today()->subDays(10),
        'airtime' => null,
    ]);
    Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)->test('dashboard.subscriptions');
    $component->set('view', 'recent');
    $rows = $component->get('allRows');

    expect($rows->first()['title'])->toBe('Aired Show')
        ->and($rows->first()['subtitle'])->toBe('S02E05')
        ->and($rows->first()['detail'])->toBe('3d');
});

it('includes same-day episodes in upcoming view', function () {
    $this->travelTo(now()->startOfDay()->addHours(12));

    $user = User::factory()->create();
    $show = Show::factory()->create(['name' => 'Today Show']);
    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 1,
        'airdate' => today(),
        'airtime' => '20:00',
    ]);
    Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)->test('dashboard.subscriptions');
    $rows = $component->get('allRows');

    expect($rows->first()['title'])->toBe('Today Show')
        ->and($rows->first()['subtitle'])->toBe('S01E01');
});

it('excludes same-day episodes from recent view', function () {
    $this->travelTo(now()->startOfDay()->addHours(12));

    $user = User::factory()->create();
    $show = Show::factory()->create(['name' => 'Today Show']);
    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 1,
        'airdate' => today(),
        'airtime' => '20:00',
    ]);
    Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)->test('dashboard.subscriptions');
    $component->set('view', 'recent');
    $rows = $component->get('allRows');

    expect($rows)->toBeEmpty();
});

it('excludes shows with no past episodes from recent view', function () {
    $user = User::factory()->create();
    $show = Show::factory()->create(['name' => 'Future Show']);
    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 1,
        'airdate' => today()->addDays(7),
        'airtime' => null,
    ]);
    Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)->test('dashboard.subscriptions');
    $component->set('view', 'recent');
    $rows = $component->get('allRows');

    expect($rows)->toBeEmpty();
});

it('sorts recent subscriptions by most recent first', function () {
    $this->travelTo(now()->startOfDay()->addHours(12));

    $user = User::factory()->create();

    $olderMovie = Movie::factory()->create([
        'title' => 'Older Movie',
        'year' => 2026,
        'digital_release_date' => today()->subDays(20),
    ]);
    $newerMovie = Movie::factory()->create([
        'title' => 'Newer Movie',
        'year' => 2026,
        'digital_release_date' => today()->subDays(3),
    ]);

    Subscription::factory()->forSubscribable($olderMovie)->create(['user_id' => $user->id]);
    Subscription::factory()->forSubscribable($newerMovie)->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)->test('dashboard.subscriptions');
    $component->set('view', 'recent');
    $rows = $component->get('allRows');

    expect($rows[0]['title'])->toBe('Newer Movie (2026)')
        ->and($rows[1]['title'])->toBe('Older Movie (2026)');
});

it('includes today\'s episodes when UTC date is ahead of user timezone', function () {
    $this->travelTo(Carbon::create(2026, 4, 20, 1, 0, 0, 'UTC'));

    $user = User::factory()->create(['timezone' => 'America/Chicago']);
    $show = Show::factory()->create(['name' => 'Late Night Show']);
    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 1,
        'airdate' => '2026-04-19',
        'airtime' => '22:00',
    ]);
    Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)->test('dashboard.subscriptions');
    $rows = $component->get('allRows');

    expect($rows->first()['title'])->toBe('Late Night Show')
        ->and($rows->first()['subtitle'])->toBe('S01E01')
        ->and($rows->first()['detail'])->not->toBe('Unknown');
});
