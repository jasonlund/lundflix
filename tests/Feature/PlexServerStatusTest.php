<?php

use App\Models\PlexMediaServer;
use App\Models\User;
use Illuminate\Foundation\Console\QueuedCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
});

it('displays online plex servers from database', function () {
    $user = User::factory()->withPlex()->create();

    PlexMediaServer::factory()->create([
        'name' => 'Home Server',
        'is_online' => true,
        'owned' => true,
    ]);

    PlexMediaServer::factory()->create([
        'name' => 'Friend Server',
        'is_online' => true,
        'owned' => false,
    ]);

    $this->actingAs($user);

    Livewire::test('plex.server-status')
        ->assertSee('Home Server')
        ->assertSee('Friend Server')
        ->assertSee('Owned');
});

it('only displays online servers', function () {
    $user = User::factory()->withPlex()->create();

    PlexMediaServer::factory()->create([
        'name' => 'Online Server',
        'is_online' => true,
    ]);

    PlexMediaServer::factory()->offline()->create([
        'name' => 'Offline Server',
    ]);

    $this->actingAs($user);

    Livewire::test('plex.server-status')
        ->assertSee('Online Server')
        ->assertDontSee('Offline Server');
});

it('queues sync command when no servers exist', function () {
    $user = User::factory()->withPlex()->create();

    $this->actingAs($user);

    Livewire::test('plex.server-status')
        ->assertSee('No servers available');

    Queue::assertPushed(QueuedCommand::class, function (QueuedCommand $job) {
        return $job->displayName() === 'plex:sync-servers';
    });
});

it('does not queue sync command when servers exist', function () {
    $user = User::factory()->withPlex()->create();

    PlexMediaServer::factory()->create(['is_online' => true]);

    $this->actingAs($user);

    Livewire::test('plex.server-status');

    Queue::assertNotPushed(QueuedCommand::class);
});

it('shows empty state when all servers are offline', function () {
    $user = User::factory()->withPlex()->create();

    PlexMediaServer::factory()->offline()->create([
        'name' => 'Offline Server',
    ]);

    $this->actingAs($user);

    Livewire::test('plex.server-status')
        ->assertSee('No servers available');
});

it('is displayed on the dashboard', function () {
    $user = User::factory()->withPlex()->create();

    PlexMediaServer::factory()->create(['is_online' => true]);

    $this->actingAs($user);

    $response = $this->get(route('home'));

    $response->assertSeeLivewire('plex.server-status');
});
