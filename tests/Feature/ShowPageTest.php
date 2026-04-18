<?php

use App\Enums\ShowStatus;
use App\Models\Show;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Vite as ViteFacade;
use Illuminate\Support\HtmlString;
use Livewire\Livewire;

beforeEach(function () {
    ViteFacade::clearResolvedInstance();

    $this->swap(Vite::class, new class extends Vite
    {
        public function __invoke($entrypoints, $buildDirectory = null): HtmlString
        {
            return new HtmlString('');
        }

        public function asset($asset, $buildDirectory = null): string
        {
            return "/{$asset}";
        }
    });
});

it('requires authentication to view show page', function () {
    $show = Show::factory()->create();

    $this->get(route('shows.show', $show))
        ->assertRedirect(route('login'));
});

it('displays show page for authenticated users', function () {
    $user = User::factory()->create();
    $show = Show::factory()->create([
        'name' => 'Breaking Bad',
    ]);

    $this->actingAs($user)
        ->get(route('shows.show', $show))
        ->assertSuccessful()
        ->assertSeeLivewire('shows.show')
        ->assertSee($show->name);
});

it('displays show page when bound by imdb id', function () {
    $user = User::factory()->create();
    $show = Show::factory()->create([
        'name' => 'Breaking Bad',
        'imdb_id' => 'tt0903747',
    ]);

    $this->actingAs($user)
        ->get(route('shows.show', ['show' => $show->imdb_id]))
        ->assertSuccessful()
        ->assertSeeLivewire('shows.show')
        ->assertSee($show->name);
});

it('displays show details', function () {
    $show = Show::factory()->create([
        'name' => 'Breaking Bad',
        'status' => ShowStatus::Ended->value,
        'runtime' => 60,
        'premiered' => now()->subYears(10),
        'ended' => now()->subYears(7),
    ]);

    Livewire::test('shows.show', ['show' => $show])
        ->assertSee('Breaking Bad')
        ->assertSee('Ended')
        ->assertSee('1h')
        ->assertSeeHtml('class="relative overflow-hidden"');
});

it('displays approximate runtime when only average_runtime is set', function () {
    $show = Show::factory()->create([
        'name' => 'Pluribus',
        'runtime' => null,
        'average_runtime' => 49,
    ]);

    Livewire::test('shows.show', ['show' => $show])
        ->assertSee('~49m');
});

it('displays exact runtime without tilde when runtime is set', function () {
    $show = Show::factory()->create([
        'name' => 'The Wire',
        'runtime' => 60,
        'average_runtime' => 60,
    ]);

    Livewire::test('shows.show', ['show' => $show])
        ->assertSee('1h')
        ->assertDontSee('~1h');
});

it('displays no runtime when neither runtime nor average_runtime is set', function () {
    $show = Show::factory()->create([
        'runtime' => null,
        'average_runtime' => null,
    ]);

    expect($show->displayRuntime())->toBeNull();

    Livewire::test('shows.show', ['show' => $show])
        ->assertDontSee('m</span>');
});

it('displays show with all details', function () {
    $show = Show::factory()->create([
        'name' => 'Game of Thrones',
        'genres' => ['Drama', 'Fantasy'],
        'network' => ['name' => 'HBO', 'country' => ['name' => 'United States']],
    ]);

    Livewire::test('shows.show', ['show' => $show])
        ->assertSee('Game of Thrones')
        ->assertSee('Drama')
        ->assertSee('Fantasy')
        ->assertSee('HBO');
});

it('displays compact schedule with single day and time', function () {
    $show = Show::factory()->create([
        'status' => ShowStatus::Running->value,
        'schedule' => ['days' => ['Monday'], 'time' => '21:00'],
    ]);

    Livewire::test('shows.show', ['show' => $show])
        ->assertSee('Mo 9p');
});

it('displays compact schedule with single day and no time', function () {
    $show = Show::factory()->create([
        'status' => ShowStatus::Running->value,
        'schedule' => ['days' => ['Friday'], 'time' => ''],
    ]);

    Livewire::test('shows.show', ['show' => $show])
        ->assertSee('Fr')
        ->assertDontSee('Fr ');
});

it('displays compact schedule with consecutive day range', function () {
    $show = Show::factory()->create([
        'status' => ShowStatus::Running->value,
        'schedule' => ['days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'], 'time' => ''],
    ]);

    Livewire::test('shows.show', ['show' => $show])
        ->assertSeeText('Mo–Fr');
});

it('displays compact schedule with non-consecutive days and time', function () {
    $show = Show::factory()->create([
        'status' => ShowStatus::Running->value,
        'schedule' => ['days' => ['Monday', 'Wednesday', 'Friday'], 'time' => '21:00'],
    ]);

    Livewire::test('shows.show', ['show' => $show])
        ->assertSee('Mo, We, Fr 9p');
});

it('hides schedule when days are empty', function () {
    $show = Show::factory()->create([
        'status' => ShowStatus::Running->value,
        'schedule' => ['days' => [], 'time' => ''],
    ]);

    $component = Livewire::test('shows.show', ['show' => $show]);

    expect($component->instance()->scheduleLabel())->toBeNull();
});

it('displays compact schedule with non-zero minutes', function () {
    $show = Show::factory()->create([
        'status' => ShowStatus::Running->value,
        'schedule' => ['days' => ['Sunday'], 'time' => '10:30'],
    ]);

    Livewire::test('shows.show', ['show' => $show])
        ->assertSee('Su 10:30a');
});

it('displays compact schedule with midnight time', function () {
    $show = Show::factory()->create([
        'status' => ShowStatus::Running->value,
        'schedule' => ['days' => ['Saturday'], 'time' => '00:00'],
    ]);

    Livewire::test('shows.show', ['show' => $show])
        ->assertSee('Sa 12a');
});

it('displays compact schedule with weekend range and time', function () {
    $show = Show::factory()->create([
        'status' => ShowStatus::Running->value,
        'schedule' => ['days' => ['Saturday', 'Sunday'], 'time' => '20:00'],
    ]);

    Livewire::test('shows.show', ['show' => $show])
        ->assertSee('Sa, Su 8p');
});

it('hides schedule for ended shows', function () {
    $show = Show::factory()->create([
        'status' => ShowStatus::Ended->value,
        'schedule' => ['days' => ['Monday'], 'time' => '21:00'],
    ]);

    Livewire::test('shows.show', ['show' => $show])
        ->assertDontSee('Mo 9p');
});

it('shows schedule for to-be-determined shows', function () {
    $show = Show::factory()->create([
        'status' => ShowStatus::ToBeDetermined->value,
        'schedule' => ['days' => ['Wednesday'], 'time' => '22:00'],
    ]);

    Livewire::test('shows.show', ['show' => $show])
        ->assertSee('We 10p');
});

it('shifts schedule day forward when timezone conversion crosses midnight', function () {
    $this->travelTo(Carbon::parse('2026-07-01'));

    $user = User::factory()->create(['timezone' => 'Europe/London']);
    $this->actingAs($user);

    // Thu 21:00 Eastern = Fri 02:00 BST (summer)
    $show = Show::factory()->create([
        'status' => ShowStatus::Running->value,
        'schedule' => ['days' => ['Thursday'], 'time' => '21:00'],
        'network' => ['id' => 1, 'name' => 'NBC', 'country' => ['name' => 'United States', 'timezone' => 'America/New_York']],
    ]);

    $component = Livewire::test('shows.show', ['show' => $show]);

    expect($component->instance()->scheduleLabel())->toBe('Fr 2a');
});

it('shifts schedule day backward when timezone conversion crosses midnight', function () {
    $this->travelTo(Carbon::parse('2026-07-01'));

    $user = User::factory()->create(['timezone' => 'America/Los_Angeles']);
    $this->actingAs($user);

    // Fri 01:00 BST = Thu 17:00 PDT (summer)
    $show = Show::factory()->create([
        'status' => ShowStatus::Running->value,
        'schedule' => ['days' => ['Friday'], 'time' => '01:00'],
        'network' => ['id' => 1, 'name' => 'BBC One', 'country' => ['name' => 'United Kingdom', 'timezone' => 'Europe/London']],
    ]);

    $component = Livewire::test('shows.show', ['show' => $show]);

    expect($component->instance()->scheduleLabel())->toBe('Th 5p');
});

it('wraps Sunday to Monday when timezone shifts day forward', function () {
    $this->travelTo(Carbon::parse('2026-07-01'));

    $user = User::factory()->create(['timezone' => 'Europe/London']);
    $this->actingAs($user);

    // Sun 22:00 Eastern = Mon 03:00 BST (summer)
    $show = Show::factory()->create([
        'status' => ShowStatus::Running->value,
        'schedule' => ['days' => ['Sunday'], 'time' => '22:00'],
        'network' => ['id' => 1, 'name' => 'NBC', 'country' => ['name' => 'United States', 'timezone' => 'America/New_York']],
    ]);

    $component = Livewire::test('shows.show', ['show' => $show]);

    expect($component->instance()->scheduleLabel())->toBe('Mo 3a');
});

it('shifts multiple schedule days when timezone crosses midnight', function () {
    $this->travelTo(Carbon::parse('2026-07-01'));

    $user = User::factory()->create(['timezone' => 'Europe/London']);
    $this->actingAs($user);

    // Mon/Wed/Fri 21:00 Eastern = Tue/Thu/Sat 02:00 BST (summer)
    $show = Show::factory()->create([
        'status' => ShowStatus::Running->value,
        'schedule' => ['days' => ['Monday', 'Wednesday', 'Friday'], 'time' => '21:00'],
        'network' => ['id' => 1, 'name' => 'NBC', 'country' => ['name' => 'United States', 'timezone' => 'America/New_York']],
    ]);

    $component = Livewire::test('shows.show', ['show' => $show]);

    expect($component->instance()->scheduleLabel())->toBe('Tu, Th, Sa 2a');
});

it('displays network logo for a mapped network', function () {
    $show = Show::factory()->create([
        'network' => ['id' => 8, 'name' => 'HBO', 'country' => ['name' => 'United States']],
        'web_channel' => null,
    ]);

    Livewire::test('shows.show', ['show' => $show])
        ->assertSeeHtml('resources/images/logos/networks/hbo-us.png')
        ->assertDontSee('Network:')
        ->assertSee('HBO (US)');
});

it('displays streaming logo for a mapped web channel', function () {
    $show = Show::factory()->create([
        'network' => null,
        'web_channel' => ['id' => 1, 'name' => 'Netflix'],
    ]);

    Livewire::test('shows.show', ['show' => $show])
        ->assertSeeHtml('resources/images/logos/streaming/netflix.png')
        ->assertDontSee('Streaming:')
        ->assertSee('Netflix');
});

it('displays both network and streaming logos when both exist', function () {
    $show = Show::factory()->create([
        'network' => ['id' => 3, 'name' => 'ABC', 'country' => ['name' => 'United States']],
        'web_channel' => ['id' => 287, 'name' => 'Disney+'],
    ]);

    Livewire::test('shows.show', ['show' => $show])
        ->assertSeeHtml('resources/images/logos/networks/abc-us.png')
        ->assertSeeHtml('resources/images/logos/streaming/disney-plus.png')
        ->assertDontSee('Network:')
        ->assertDontSee('Streaming:')
        ->assertSee('ABC (US)')
        ->assertSee('Disney+');
});

it('displays text-only fallback for unmapped network', function () {
    $show = Show::factory()->create([
        'network' => ['id' => 99999, 'name' => 'Unknown TV', 'country' => ['name' => 'United States']],
        'web_channel' => null,
    ]);

    Livewire::test('shows.show', ['show' => $show])
        ->assertDontSeeHtml('resources/images/logos/')
        ->assertSee('Unknown TV (US)');
});

it('abbreviates country names in network tooltip', function () {
    $show = Show::factory()->create([
        'network' => ['id' => 12, 'name' => 'BBC One', 'country' => ['name' => 'United Kingdom']],
        'web_channel' => null,
    ]);

    Livewire::test('shows.show', ['show' => $show])
        ->assertSeeHtml('resources/images/logos/networks/bbc-one-uk.png')
        ->assertSee('BBC One (UK)')
        ->assertDontSee('United Kingdom');
});

it('displays content rating when available', function () {
    $show = Show::factory()->create([
        'name' => 'Breaking Bad',
        'content_ratings' => [
            ['rating' => 'TV-MA', 'iso_3166_1' => 'US'],
            ['rating' => '15', 'iso_3166_1' => 'GB'],
        ],
    ]);

    Livewire::test('shows.show', ['show' => $show])
        ->assertSee('TV-MA');
});

it('does not display content rating when not available', function () {
    $show = Show::factory()->create([
        'name' => 'Mystery Show',
        'content_ratings' => null,
    ]);

    Livewire::test('shows.show', ['show' => $show])
        ->assertDontSee('TV-MA')
        ->assertDontSee('TV-14');
});

it('displays US content rating from multiple countries', function () {
    $show = Show::factory()->create([
        'content_ratings' => [
            ['rating' => '15', 'iso_3166_1' => 'GB'],
            ['rating' => 'TV-14', 'iso_3166_1' => 'US'],
            ['rating' => 'M', 'iso_3166_1' => 'AU'],
        ],
    ]);

    Livewire::test('shows.show', ['show' => $show])
        ->assertSee('TV-14');
});

it('renders the cart pill', function () {
    $show = Show::factory()->create();

    Livewire::test('shows.show', ['show' => $show])
        ->assertSee('Cart');
});

describe('subscription', function () {
    it('can subscribe to a running show', function () {
        $user = User::factory()->create();
        $show = Show::factory()->create(['status' => ShowStatus::Running->value]);

        Livewire::actingAs($user)
            ->test('shows.show', ['show' => $show])
            ->assertSet('isSubscribed', false)
            ->call('toggleSubscription')
            ->assertSet('isSubscribed', true);

        expect(Subscription::query()
            ->where('user_id', $user->id)
            ->where('subscribable_type', Show::class)
            ->where('subscribable_id', $show->id)
            ->exists())->toBeTrue();
    });

    it('can unsubscribe from a show', function () {
        $user = User::factory()->create();
        $show = Show::factory()->create(['status' => ShowStatus::Running->value]);
        Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

        Livewire::actingAs($user)
            ->test('shows.show', ['show' => $show])
            ->assertSet('isSubscribed', true)
            ->call('toggleSubscription')
            ->assertSet('isSubscribed', false);

        expect(Subscription::query()
            ->where('user_id', $user->id)
            ->where('subscribable_type', Show::class)
            ->where('subscribable_id', $show->id)
            ->exists())->toBeFalse();
    });

    it('allows subscription for subscribable statuses', function (ShowStatus $status) {
        $user = User::factory()->create();
        $show = Show::factory()->create(['status' => $status->value]);

        Livewire::actingAs($user)
            ->test('shows.show', ['show' => $show])
            ->assertSet('isSubscribable', true);
    })->with([
        'Running' => ShowStatus::Running,
        'ToBeDetermined' => ShowStatus::ToBeDetermined,
        'InDevelopment' => ShowStatus::InDevelopment,
    ]);

    it('disables subscription for ended shows', function () {
        $user = User::factory()->create();
        $show = Show::factory()->create(['status' => ShowStatus::Ended->value]);

        Livewire::actingAs($user)
            ->test('shows.show', ['show' => $show])
            ->assertSet('isSubscribable', false);
    });

    it('disables subscription for shows with null status', function () {
        $user = User::factory()->create();
        $show = Show::factory()->create(['status' => null]);

        Livewire::actingAs($user)
            ->test('shows.show', ['show' => $show])
            ->assertSet('isSubscribable', false);
    });

    it('prevents toggling subscription for ended shows', function () {
        $user = User::factory()->create();
        $show = Show::factory()->create(['status' => ShowStatus::Ended->value]);

        Livewire::actingAs($user)
            ->test('shows.show', ['show' => $show])
            ->call('toggleSubscription')
            ->assertSet('isSubscribed', false);

        expect(Subscription::query()->where('user_id', $user->id)->count())->toBe(0);
    });

    it('initializes subscription state from database on mount', function () {
        $user = User::factory()->create();
        $show = Show::factory()->create(['status' => ShowStatus::Running->value]);
        Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

        Livewire::actingAs($user)
            ->test('shows.show', ['show' => $show])
            ->assertSet('isSubscribed', true);
    });
});
