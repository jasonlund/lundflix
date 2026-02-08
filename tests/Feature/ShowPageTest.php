<?php

use App\Models\Show;
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
        'status' => 'Ended',
        'runtime' => 60,
        'premiered' => now()->subYears(10),
        'ended' => now()->subYears(7),
    ]);

    Livewire::test('shows.show', ['show' => $show])
        ->assertSee('Breaking Bad')
        ->assertSee('Ended')
        ->assertSee('60 min')
        ->assertSeeHtml('class="relative aspect-video min-h-56 overflow-hidden bg-zinc-900"');
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
        ->assertSee('IMDB');
});

it('displays network logo for a mapped network', function () {
    $show = Show::factory()->create([
        'network' => ['id' => 8, 'name' => 'HBO', 'country' => ['name' => 'United States']],
        'web_channel' => null,
    ]);

    Livewire::test('shows.show', ['show' => $show])
        ->assertSeeHtml('resources/images/logos/networks/hbo-us.png')
        ->assertSee('HBO');
});

it('displays streaming logo for a mapped web channel', function () {
    $show = Show::factory()->create([
        'network' => null,
        'web_channel' => ['id' => 1, 'name' => 'Netflix'],
    ]);

    Livewire::test('shows.show', ['show' => $show])
        ->assertSeeHtml('resources/images/logos/streaming/netflix.png')
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
        ->assertSee('Network:')
        ->assertSee('Streaming:');
});

it('displays text-only fallback for unmapped network', function () {
    $show = Show::factory()->create([
        'network' => ['id' => 99999, 'name' => 'Unknown TV', 'country' => ['name' => 'United States']],
        'web_channel' => null,
    ]);

    Livewire::test('shows.show', ['show' => $show])
        ->assertDontSeeHtml('resources/images/logos/')
        ->assertSee('Unknown TV');
});
