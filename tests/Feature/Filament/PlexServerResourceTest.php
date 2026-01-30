<?php

use App\Filament\Resources\PlexServers\Pages\ListPlexServers;
use App\Models\PlexMediaServer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.plex.seed_token' => 'admin-token']);
    $this->admin = User::factory()->create(['plex_token' => 'admin-token']);
    $this->actingAs($this->admin);
});

it('can render the list page', function () {
    Livewire::test(ListPlexServers::class)
        ->assertSuccessful();
});

it('displays plex servers in the list', function () {
    PlexMediaServer::factory()->create([
        'name' => 'Home Media Server',
        'is_online' => true,
    ]);

    Livewire::test(ListPlexServers::class)
        ->assertSee('Home Media Server');
});

it('has visible toggle column', function () {
    PlexMediaServer::factory()->create();

    Livewire::test(ListPlexServers::class)
        ->assertTableColumnExists('visible');
});

it('shows sync servers action', function () {
    Livewire::test(ListPlexServers::class)
        ->assertActionExists('sync');
});
