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
        ->assertSee('Test Movie')
        ->assertSee('2024');
});

it('is hidden when user has no subscriptions', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create(['title' => 'Some Movie', 'year' => 2024]);

    $this->actingAs($user)
        ->get('/')
        ->assertSuccessful()
        ->assertDontSee('Some Movie');
});

it('shows date for upcoming movie over a week away', function () {
    $this->travelTo(Carbon::create(2026, 6, 1, 12, 0, 0, 'UTC'));

    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'title' => 'Upcoming Movie',
        'year' => 2026,
        'digital_release_date' => '2026-06-11',
    ]);
    Subscription::factory()->forSubscribable($movie)->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)->test('dashboard.subscriptions');
    $rows = $component->get('allRows');

    expect($rows->first()['detail'])->toBe('6/11');
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

    expect($rows->first()['detail'])->toBe('TBD');
});

it('shows weekday and time for upcoming episode within a week', function () {
    $this->travelTo(Carbon::create(2026, 6, 1, 12, 0, 0, 'UTC'));

    $user = User::factory()->create();
    $show = Show::factory()->create(['name' => 'Cool Show']);
    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 5,
        'airdate' => '2026-06-05',
        'airtime' => null,
    ]);
    Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)->test('dashboard.subscriptions');
    $rows = $component->get('allRows');

    // June 5 midnight ET (factory default NBC network) → midnight ET
    expect($rows->first()['detail'])->toBe('Fr 12a');
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

    expect($rows->first()['detail'])->toBe('TBD');
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

    expect($rows[0]['title'])->toBe('Sooner Movie');
    expect($rows[1]['title'])->toBe('Later Movie');
    expect($rows[2]['title'])->toBe('No Date Movie');
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

it('shows date for recently released movies in recent view', function () {
    $this->travelTo(Carbon::create(2026, 6, 10, 12, 0, 0, 'UTC'));

    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'title' => 'Released Movie',
        'year' => 2026,
        'digital_release_date' => '2026-06-10',
    ]);
    Subscription::factory()->forSubscribable($movie)->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)->test('dashboard.subscriptions');
    $component->set('view', 'recent');
    $rows = $component->get('allRows');

    expect($rows->first()['title'])->toBe('Released Movie')
        ->and($rows->first()['subtitle'])->toBe('2026')
        ->and($rows->first()['detail'])->toBe('6/10');
});

it('falls back to release_date for recent movies without digital_release_date', function () {
    $this->travelTo(Carbon::create(2026, 6, 7, 12, 0, 0, 'UTC'));

    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'title' => 'Theater Movie',
        'year' => 2026,
        'digital_release_date' => null,
        'release_date' => '2026-06-07',
    ]);
    Subscription::factory()->forSubscribable($movie)->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)->test('dashboard.subscriptions');
    $component->set('view', 'recent');
    $rows = $component->get('allRows');

    expect($rows->first()['title'])->toBe('Theater Movie')
        ->and($rows->first()['subtitle'])->toBe('2026')
        ->and($rows->first()['detail'])->toBe('6/7');
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

it('shows date and time for most recently aired episode in recent view', function () {
    // June 8 noon UTC = June 8 8 AM ET
    $this->travelTo(Carbon::create(2026, 6, 8, 12, 0, 0, 'UTC'));

    $user = User::factory()->create();
    $show = Show::factory()->create(['name' => 'Aired Show']);
    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 2,
        'number' => 5,
        'airdate' => '2026-06-07',
        'airtime' => '20:00',
    ]);
    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 2,
        'number' => 4,
        'airdate' => '2026-05-31',
        'airtime' => '20:00',
    ]);
    Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)->test('dashboard.subscriptions');
    $component->set('view', 'recent');
    $rows = $component->get('allRows');

    // June 7 8 PM ET in user TZ
    expect($rows->first()['title'])->toBe('Aired Show')
        ->and($rows->first()['subtitle'])->toBe('S02E05')
        ->and($rows->first()['detail'])->toBe('6/7 8p');
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

it('excludes recent items older than 48h', function () {
    $this->travelTo(Carbon::create(2026, 6, 10, 12, 0, 0, 'UTC'));

    $user = User::factory()->create();

    $oldMovie = Movie::factory()->create([
        'title' => 'Old Movie',
        'year' => 2026,
        'digital_release_date' => '2026-06-07',
    ]);
    $recentMovie = Movie::factory()->create([
        'title' => 'Recent Movie',
        'year' => 2026,
        'digital_release_date' => '2026-06-09',
    ]);

    Subscription::factory()->forSubscribable($oldMovie)->create(['user_id' => $user->id]);
    Subscription::factory()->forSubscribable($recentMovie)->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)->test('dashboard.subscriptions');
    $component->set('view', 'recent');
    $rows = $component->get('allRows');

    expect($rows)->toHaveCount(1)
        ->and($rows->first()['title'])->toBe('Recent Movie');
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
        ->and($rows->first()['detail'])->not->toBe('TBD');
});

it('shows recently aired Apple TV+ episode in upcoming via override', function () {
    // Apple TV+ drops at 6 PM PT the day before the stored airdate.
    // Travel to 8 PM PT on April 22 — episode with airdate April 23 aired 2h ago.
    $this->travelTo(Carbon::parse('2026-04-22 20:00', 'America/Los_Angeles')->utc());

    $user = User::factory()->create(['timezone' => 'America/Chicago']);
    $show = Show::factory()->appleTvPlus()->create(['name' => 'For All Mankind']);
    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 5,
        'number' => 5,
        'airdate' => '2026-04-23',
        'airtime' => null,
    ]);
    Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)->test('dashboard.subscriptions');
    $rows = $component->get('allRows');

    // 6 PM PT = 8 PM CT, aired 2h ago today — no weekday, raw relative
    expect($rows->first()['title'])->toBe('For All Mankind')
        ->and($rows->first()['subtitle'])->toBe('S05E05')
        ->and($rows->first()['detail'])->toBe('8p')
        ->and($rows->first()['relative'])->toBe('2h')
        ->and($rows->first()['recently_aired'])->toBeTrue();
});

it('shows Apple TV+ episode in recent view after it has aired via override', function () {
    // April 23 noon CT = April 23 17:00 UTC.
    // Apple TV+ episode with airdate April 23 dropped at 6 PM PT on April 22 (01:00 UTC April 23).
    $this->travelTo(Carbon::parse('2026-04-23 12:00', 'America/Chicago')->utc());

    $user = User::factory()->create(['timezone' => 'America/Chicago']);
    $show = Show::factory()->appleTvPlus()->create(['name' => 'For All Mankind']);
    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 5,
        'number' => 5,
        'airdate' => '2026-04-23',
        'airtime' => null,
    ]);
    Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)->test('dashboard.subscriptions');
    $component->set('view', 'recent');
    $rows = $component->get('allRows');

    // Apple TV+ dropped at 6 PM PT Apr 22 → 8 PM CT Apr 22
    expect($rows->first()['title'])->toBe('For All Mankind')
        ->and($rows->first()['subtitle'])->toBe('S05E05')
        ->and($rows->first()['detail'])->toBe('4/22 8p');
});

it('keeps non-override episodes with future airtime in upcoming view', function () {
    $this->travelTo(now()->startOfDay()->addHours(12));

    $user = User::factory()->create();
    $show = Show::factory()->create(['name' => 'NBC Show']);
    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 1,
        'airdate' => today(),
        'airtime' => '21:00',
    ]);
    Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)->test('dashboard.subscriptions');
    $rows = $component->get('allRows');

    expect($rows->first()['title'])->toBe('NBC Show')
        ->and($rows->first()['detail'])->not->toBe('TBD');
});

it('excludes non-override episodes with future airtime from recent view', function () {
    $this->travelTo(now()->startOfDay()->addHours(12));

    $user = User::factory()->create();
    $show = Show::factory()->create(['name' => 'NBC Show']);
    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 1,
        'airdate' => today(),
        'airtime' => '21:00',
    ]);
    Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)->test('dashboard.subscriptions');
    $component->set('view', 'recent');
    $rows = $component->get('allRows');

    expect($rows)->toBeEmpty();
});

it('shows Paramount+ episode in recent view after midnight release via dayOffset 0 override', function () {
    // Paramount+ drops at midnight PT on the airdate (dayOffset: 0, hour: 0).
    // Travel to 9 AM PT on April 23 — episode with airdate April 23 has been out since midnight.
    $this->travelTo(Carbon::parse('2026-04-23 09:00', 'America/Los_Angeles')->utc());

    $user = User::factory()->create(['timezone' => 'America/Chicago']);
    $show = Show::factory()->paramountPlus()->create(['name' => 'Lioness']);
    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 2,
        'number' => 3,
        'airdate' => '2026-04-23',
        'airtime' => null,
    ]);
    Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)->test('dashboard.subscriptions');
    $component->set('view', 'recent');
    $rows = $component->get('allRows');

    expect($rows->first()['title'])->toBe('Lioness')
        ->and($rows->first()['subtitle'])->toBe('S02E03');
});

it('shows time only for upcoming episode within 24 hours', function () {
    $this->travelTo(Carbon::create(2026, 6, 1, 12, 0, 0, 'UTC'));

    $user = User::factory()->create();
    $show = Show::factory()->create(['name' => 'Tonight Show']);
    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 1,
        'airdate' => '2026-06-01',
        'airtime' => '20:00',
    ]);
    Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)->test('dashboard.subscriptions');
    $rows = $component->get('allRows');

    // 8 PM ET (NBC network default), ~12 hours from noon UTC (8 AM ET)
    expect($rows->first()['detail'])->toBe('8p');
});

it('shows date and time for upcoming episode over a week away', function () {
    $this->travelTo(Carbon::create(2026, 6, 1, 12, 0, 0, 'UTC'));

    $user = User::factory()->create();
    $show = Show::factory()->create(['name' => 'Far Show']);
    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 1,
        'airdate' => '2026-06-15',
        'airtime' => '21:00',
    ]);
    Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)->test('dashboard.subscriptions');
    $rows = $component->get('allRows');

    // 9 PM ET on June 15 (same TZ, no day shift)
    expect($rows->first()['detail'])->toBe('6/15 9p');
});

it('shows date with year for upcoming episode in a different year', function () {
    $this->travelTo(Carbon::create(2026, 12, 20, 12, 0, 0, 'UTC'));

    $user = User::factory()->create();
    $show = Show::factory()->create(['name' => 'New Year Show']);
    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 1,
        'airdate' => '2027-01-10',
        'airtime' => '20:00',
    ]);
    Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)->test('dashboard.subscriptions');
    $rows = $component->get('allRows');

    // 8 PM EST on Jan 10 (same TZ)
    expect($rows->first()['detail'])->toBe('1/10/27 8p');
});

it('shows weekday for upcoming movie within a week', function () {
    $this->travelTo(Carbon::create(2026, 6, 1, 12, 0, 0, 'UTC'));

    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'title' => 'Soon Movie',
        'year' => 2026,
        'digital_release_date' => '2026-06-04',
    ]);
    Subscription::factory()->forSubscribable($movie)->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)->test('dashboard.subscriptions');
    $rows = $component->get('allRows');

    // June 4, 2026 is a Thursday
    expect($rows->first()['detail'])->toBe('Th');
});

it('includes url in row data for movies', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create(['title' => 'URL Movie', 'year' => 2026]);
    Subscription::factory()->forSubscribable($movie)->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)->test('dashboard.subscriptions');
    $rows = $component->get('allRows');

    expect($rows->first()['url'])->toBe(route('movies.show', $movie));
});

it('includes url in row data for shows', function () {
    $user = User::factory()->create();
    $show = Show::factory()->create(['name' => 'URL Show']);
    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 1,
        'airdate' => now()->addDays(3),
        'airtime' => null,
    ]);
    Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)->test('dashboard.subscriptions');
    $rows = $component->get('allRows');

    expect($rows->first()['url'])->toBe(route('shows.show', $show));
});

it('excludes past movies from upcoming view', function () {
    $this->travelTo(Carbon::create(2026, 6, 10, 12, 0, 0, 'UTC'));

    $user = User::factory()->create();
    $pastMovie = Movie::factory()->create([
        'title' => 'Past Movie',
        'year' => 2026,
        'digital_release_date' => '2026-06-05',
    ]);
    $futureMovie = Movie::factory()->create([
        'title' => 'Future Movie',
        'year' => 2026,
        'digital_release_date' => '2026-06-15',
    ]);

    Subscription::factory()->forSubscribable($pastMovie)->create(['user_id' => $user->id]);
    Subscription::factory()->forSubscribable($futureMovie)->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)->test('dashboard.subscriptions');
    $rows = $component->get('allRows');

    expect($rows)->toHaveCount(1)
        ->and($rows->first()['title'])->toBe('Future Movie');
});

it('sorts recent subscriptions by most recent first', function () {
    $this->travelTo(Carbon::create(2026, 6, 10, 12, 0, 0, 'UTC'));

    $user = User::factory()->create();

    $olderMovie = Movie::factory()->create([
        'title' => 'Older Movie',
        'year' => 2026,
        'digital_release_date' => '2026-06-09',
    ]);
    $newerMovie = Movie::factory()->create([
        'title' => 'Newer Movie',
        'year' => 2026,
        'digital_release_date' => '2026-06-10',
    ]);

    Subscription::factory()->forSubscribable($olderMovie)->create(['user_id' => $user->id]);
    Subscription::factory()->forSubscribable($newerMovie)->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)->test('dashboard.subscriptions');
    $component->set('view', 'recent');
    $rows = $component->get('allRows');

    expect($rows[0]['title'])->toBe('Newer Movie')
        ->and($rows[1]['title'])->toBe('Older Movie');
});

it('shows empty state in recent view when no items within 48h', function () {
    $this->travelTo(Carbon::create(2026, 6, 10, 12, 0, 0, 'UTC'));

    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'title' => 'Old Movie',
        'year' => 2026,
        'digital_release_date' => '2026-06-01',
    ]);
    Subscription::factory()->forSubscribable($movie)->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('dashboard.subscriptions')
        ->set('view', 'recent')
        ->assertSee(__('lundbergh.dashboard.no_recent_subscriptions'));
});

it('keeps recently released movies in upcoming view within 48h', function () {
    $this->travelTo(Carbon::create(2026, 6, 10, 12, 0, 0, 'UTC'));

    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'title' => 'Just Released',
        'year' => 2026,
        'digital_release_date' => '2026-06-10',
    ]);
    Subscription::factory()->forSubscribable($movie)->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)->test('dashboard.subscriptions');
    $rows = $component->get('allRows');

    expect($rows)->toHaveCount(1)
        ->and($rows->first()['title'])->toBe('Just Released')
        ->and($rows->first()['recently_aired'])->toBeTrue();
});

it('shows recently aired episode in upcoming view within 48h', function () {
    // June 10 noon UTC = June 10 8 AM ET
    $this->travelTo(Carbon::create(2026, 6, 10, 12, 0, 0, 'UTC'));

    $user = User::factory()->create();
    $show = Show::factory()->create(['name' => 'Survivor']);
    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 50,
        'number' => 13,
        'airdate' => '2026-06-09',
        'airtime' => '20:00',
    ]);
    Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)->test('dashboard.subscriptions');
    $rows = $component->get('allRows');

    // June 9 (Tuesday) 8 PM ET = yesterday, so weekday + time format
    expect($rows)->toHaveCount(1)
        ->and($rows->first()['title'])->toBe('Survivor')
        ->and($rows->first()['subtitle'])->toBe('S50E13')
        ->and($rows->first()['detail'])->toBe('Tu 8p')
        ->and($rows->first()['recently_aired'])->toBeTrue();
});

it('shows both recently aired and next upcoming episode for same show', function () {
    // June 10 noon UTC = June 10 8 AM ET
    $this->travelTo(Carbon::create(2026, 6, 10, 12, 0, 0, 'UTC'));

    $user = User::factory()->create();
    $show = Show::factory()->create(['name' => 'Weekly Show']);
    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 5,
        'airdate' => '2026-06-09',
        'airtime' => '20:00',
    ]);
    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 6,
        'airdate' => '2026-06-16',
        'airtime' => '20:00',
    ]);
    Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)->test('dashboard.subscriptions');
    $rows = $component->get('allRows');

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['subtitle'])->toBe('S01E05')
        ->and($rows[0]['recently_aired'])->toBeTrue()
        ->and($rows[1]['subtitle'])->toBe('S01E06')
        ->and($rows[1]['recently_aired'])->toBeFalse();
});
