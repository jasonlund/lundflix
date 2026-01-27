<?php

use App\Filament\Resources\Shows\Pages\ListShows;
use App\Filament\Resources\Shows\Pages\ViewShow;
use App\Models\Show;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    config(['services.plex.seed_token' => 'admin-token']);
    $this->admin = User::factory()->create(['plex_token' => 'admin-token']);
    $this->actingAs($this->admin);
});

it('can render the list page', function () {
    Livewire::test(ListShows::class)
        ->assertSuccessful();
});

it('can render the view page', function () {
    $show = Show::factory()->create();

    Livewire::test(ViewShow::class, ['record' => $show->getRouteKey()])
        ->assertSuccessful();
});

it('displays shows in the list', function () {
    $show = Show::factory()->create([
        'name' => 'Test Show Name',
        'imdb_id' => 'tt7654321',
    ]);

    Livewire::test(ListShows::class)
        ->assertSee('Test Show Name')
        ->assertSee('tt7654321');
});

it('displays show details on view page', function () {
    $show = Show::factory()->create([
        'name' => 'Detailed Show',
        'imdb_id' => 'tt8888888',
        'status' => 'Running',
    ]);

    Livewire::test(ViewShow::class, ['record' => $show->getRouteKey()])
        ->assertSee('Detailed Show')
        ->assertSee('tt8888888')
        ->assertSee('Running');
});

it('does not show create button due to policy', function () {
    Livewire::test(ListShows::class)
        ->assertDontSee('New show');
});

it('does not show edit action due to policy', function () {
    $show = Show::factory()->create();

    Livewire::test(ViewShow::class, ['record' => $show->getRouteKey()])
        ->assertDontSee('Edit');
});
