<?php

use App\Enums\ShowStatus;
use App\Models\Episode;
use App\Models\Show;
use App\Services\CartService;
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
        ->assertSee('HBO')
        ->assertSee('IMDb');
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

it('displays cart episode count when episodes are in cart', function () {
    $show = Show::factory()->create();

    app(CartService::class)->syncShowEpisodes($show->id, ['S01E01', 'S01E02', 'S01E03']);

    Livewire::test('shows.show', ['show' => $show])
        ->assertSee('3')
        ->assertSee('Add/Remove Episodes Below');
});

it('displays dash when no episodes are in cart', function () {
    $show = Show::factory()->create();

    Livewire::test('shows.show', ['show' => $show])
        ->assertSee('Add/Remove Episodes Below');
});

it('updates cart episode count on cart-updated event', function () {
    $show = Show::factory()->create();

    $component = Livewire::test('shows.show', ['show' => $show])
        ->assertSet('cartEpisodeCount', 0);

    app(CartService::class)->syncShowEpisodes($show->id, ['S01E01', 'S01E02']);

    $component->dispatch('cart-updated')
        ->assertSet('cartEpisodeCount', 2);
});

it('displays check mark when all episodes are in cart', function () {
    $show = Show::factory()->create();
    Episode::factory()->for($show)->create(['season' => 1, 'number' => 1]);
    Episode::factory()->for($show)->create(['season' => 1, 'number' => 2]);
    $show->load('episodes');

    app(CartService::class)->syncShowEpisodes($show->id, ['S01E01', 'S01E02']);

    Livewire::test('shows.show', ['show' => $show])
        ->assertSeeHtml('m4.5 12.75 6 6 9-13.5')
        ->assertSet('cartEpisodeCount', 2)
        ->assertSet('totalEpisodeCount', 2);
});

it('displays count instead of check mark when not all episodes are in cart', function () {
    $show = Show::factory()->create();
    Episode::factory()->for($show)->create(['season' => 1, 'number' => 1]);
    Episode::factory()->for($show)->create(['season' => 1, 'number' => 2]);
    Episode::factory()->for($show)->create(['season' => 1, 'number' => 3]);
    $show->load('episodes');

    app(CartService::class)->syncShowEpisodes($show->id, ['S01E01', 'S01E02']);

    Livewire::test('shows.show', ['show' => $show])
        ->assertDontSeeHtml('m4.5 12.75 6 6 9-13.5')
        ->assertSee('2');
});
