<?php

use App\Models\PlexMediaServer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('displays visible plex servers from database', function () {
    $user = User::factory()->withPlex()->create();

    PlexMediaServer::factory()->create([
        'name' => 'Home Server',
        'is_online' => true,
        'visible' => true,
        'owned' => true,
    ]);

    PlexMediaServer::factory()->create([
        'name' => 'Friend Server',
        'is_online' => true,
        'visible' => true,
        'owned' => false,
    ]);

    $this->actingAs($user);

    Livewire::test('plex.server-status')
        ->assertSee('Home Server')
        ->assertSee('Friend Server');
});

it('shows both online and offline visible servers', function () {
    $user = User::factory()->withPlex()->create();

    PlexMediaServer::factory()->create([
        'name' => 'Online Server',
        'is_online' => true,
        'visible' => true,
    ]);

    PlexMediaServer::factory()->offline()->create([
        'name' => 'Offline Server',
        'visible' => true,
    ]);

    $this->actingAs($user);

    Livewire::test('plex.server-status')
        ->assertSee('Online Server')
        ->assertSee('Offline Server');
});

it('only displays visible servers', function () {
    $user = User::factory()->withPlex()->create();

    PlexMediaServer::factory()->create([
        'name' => 'Visible Server',
        'is_online' => true,
        'visible' => true,
    ]);

    PlexMediaServer::factory()->create([
        'name' => 'Hidden Server',
        'is_online' => true,
        'visible' => false,
    ]);

    $this->actingAs($user);

    Livewire::test('plex.server-status')
        ->assertSee('Visible Server')
        ->assertDontSee('Hidden Server');
});

it('shows empty state when no servers are visible', function () {
    $user = User::factory()->withPlex()->create();

    PlexMediaServer::factory()->create([
        'name' => 'Hidden Server',
        'is_online' => true,
        'visible' => false,
    ]);

    $this->actingAs($user);

    Livewire::test('plex.server-status')
        ->assertSee('No servers available');
});

it('displays owner thumb avatar when available', function () {
    $user = User::factory()->withPlex()->create();

    PlexMediaServer::factory()->create([
        'name' => 'My Server',
        'is_online' => true,
        'visible' => true,
        'owner_thumb' => 'https://plex.tv/users/dc2101cf70149f3c/avatar?c=1771718676',
    ]);

    $this->actingAs($user);

    Livewire::test('plex.server-status')
        ->assertSeeHtml('https://plex.tv/users/dc2101cf70149f3c/avatar?c=1771718676');
});

it('displays server initials when owner thumb is missing', function () {
    $user = User::factory()->withPlex()->create();

    PlexMediaServer::factory()->create([
        'name' => 'My Server',
        'is_online' => true,
        'visible' => true,
        'owner_thumb' => null,
    ]);

    $this->actingAs($user);

    Livewire::test('plex.server-status')
        ->assertDontSeeHtml('plex.tv/users')
        ->assertSee('My Server');
});

it('shows last seen time for offline servers', function () {
    $user = User::factory()->withPlex()->create();

    PlexMediaServer::factory()->offline()->create([
        'name' => 'Down Server',
        'visible' => true,
        'last_seen_at' => now()->subHours(3),
    ]);

    $this->actingAs($user);

    Livewire::test('plex.server-status')
        ->assertSee('3h');
});

it('is displayed on the dashboard', function () {
    $user = User::factory()->withPlex()->create();

    PlexMediaServer::factory()->create([
        'is_online' => true,
        'visible' => true,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('home'));

    $response->assertSeeLivewire('plex.server-status');
});
