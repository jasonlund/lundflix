<?php

use App\Filament\Resources\Requests\Pages\ViewRequest;
use App\Filament\Resources\Requests\RelationManagers\RequestItemsRelationManager;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\Show;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    config(['services.plex.seed_token' => 'admin-token']);
    $this->admin = User::factory()->create(['plex_token' => 'admin-token']);
    $this->actingAs($this->admin);
});

it('renders the request items relation manager on view page', function () {
    $request = Request::factory()->create();

    Livewire::test(ViewRequest::class, ['record' => $request->getRouteKey()])
        ->assertSeeLivewire(RequestItemsRelationManager::class);
});

it('displays movie request items', function () {
    $request = Request::factory()->create();
    $movie = Movie::factory()->create(['title' => 'Test Movie', 'year' => 2024]);
    $item = RequestItem::factory()->create([
        'request_id' => $request->id,
        'requestable_type' => Movie::class,
        'requestable_id' => $movie->id,
    ]);

    Livewire::test(RequestItemsRelationManager::class, [
        'ownerRecord' => $request,
        'pageClass' => ViewRequest::class,
    ])
        ->assertOk()
        ->assertCanSeeTableRecords([$item])
        ->assertSee('Test Movie (2024)');
});

it('displays episode request items', function () {
    $request = Request::factory()->create();
    $show = Show::factory()->create(['name' => 'Test Show']);
    $episode = Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 5,
    ]);
    $item = RequestItem::factory()->create([
        'request_id' => $request->id,
        'requestable_type' => Episode::class,
        'requestable_id' => $episode->id,
    ]);

    Livewire::test(RequestItemsRelationManager::class, [
        'ownerRecord' => $request,
        'pageClass' => ViewRequest::class,
    ])
        ->assertOk()
        ->assertCanSeeTableRecords([$item])
        ->assertSee('Test Show')
        ->assertSee('s01e05');
});

it('displays type badges for movies and episodes', function () {
    $request = Request::factory()->create();
    $movie = Movie::factory()->create();
    $show = Show::factory()->create();
    $episode = Episode::factory()->create(['show_id' => $show->id]);

    $movieItem = RequestItem::factory()->create([
        'request_id' => $request->id,
        'requestable_type' => Movie::class,
        'requestable_id' => $movie->id,
    ]);
    $episodeItem = RequestItem::factory()->create([
        'request_id' => $request->id,
        'requestable_type' => Episode::class,
        'requestable_id' => $episode->id,
    ]);

    Livewire::test(RequestItemsRelationManager::class, [
        'ownerRecord' => $request,
        'pageClass' => ViewRequest::class,
    ])
        ->assertOk()
        ->assertCanSeeTableRecords([$movieItem, $episodeItem])
        ->assertSee('Movie')
        ->assertSee('Episode');
});

it('is read-only and does not show header actions', function () {
    $request = Request::factory()->create();

    Livewire::test(RequestItemsRelationManager::class, [
        'ownerRecord' => $request,
        'pageClass' => ViewRequest::class,
    ])
        ->assertOk()
        ->assertDontSee('New request item');
});
