<?php

use App\Filament\Resources\Shows\Pages\ViewShow;
use App\Filament\Resources\Shows\RelationManagers\EpisodesRelationManager;
use App\Models\Episode;
use App\Models\Show;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('renders the episodes relation manager on view page', function () {
    $show = Show::factory()->create();

    Livewire::test(ViewShow::class, ['record' => $show->getRouteKey()])
        ->assertSeeLivewire(EpisodesRelationManager::class);
});

it('displays episodes in the relation manager', function () {
    $show = Show::factory()->create();
    $episode = Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 5,
        'name' => 'The Test Episode',
    ]);

    Livewire::test(EpisodesRelationManager::class, [
        'ownerRecord' => $show,
        'pageClass' => ViewShow::class,
    ])
        ->assertOk()
        ->assertCanSeeTableRecords([$episode])
        ->assertSee('The Test Episode')
        ->assertSee('s01e05');
});

it('displays special episodes with correct code', function () {
    $show = Show::factory()->create();
    $episode = Episode::factory()->special()->create([
        'show_id' => $show->id,
        'season' => 2,
        'number' => 1,
        'name' => 'Special Episode',
    ]);

    Livewire::test(EpisodesRelationManager::class, [
        'ownerRecord' => $show,
        'pageClass' => ViewShow::class,
    ])
        ->assertOk()
        ->assertSee('Special Episode')
        ->assertSee('s02s01');
});

it('can filter episodes by season', function () {
    $show = Show::factory()->create();
    $s1Episode = Episode::factory()->create(['show_id' => $show->id, 'season' => 1, 'number' => 1]);
    $s2Episode = Episode::factory()->create(['show_id' => $show->id, 'season' => 2, 'number' => 1]);

    Livewire::test(EpisodesRelationManager::class, [
        'ownerRecord' => $show,
        'pageClass' => ViewShow::class,
    ])
        ->filterTable('season', 1)
        ->assertCanSeeTableRecords([$s1Episode])
        ->assertCanNotSeeTableRecords([$s2Episode]);
});

it('can filter episodes by type', function () {
    $show = Show::factory()->create();
    $regular = Episode::factory()->create(['show_id' => $show->id, 'season' => 1, 'number' => 1, 'type' => 'regular']);
    $special = Episode::factory()->special()->create(['show_id' => $show->id, 'season' => 1, 'number' => 2]);

    Livewire::test(EpisodesRelationManager::class, [
        'ownerRecord' => $show,
        'pageClass' => ViewShow::class,
    ])
        ->filterTable('type', 'significant_special')
        ->assertCanSeeTableRecords([$special])
        ->assertCanNotSeeTableRecords([$regular]);
});

it('can search episodes by name', function () {
    $show = Show::factory()->create();
    $matchingEpisode = Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 1,
        'name' => 'The Pilot Episode',
    ]);
    $otherEpisode = Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 9,
        'number' => 15,
        'name' => 'The Finale',
    ]);

    Livewire::test(EpisodesRelationManager::class, [
        'ownerRecord' => $show,
        'pageClass' => ViewShow::class,
    ])
        ->searchTable('Pilot')
        ->assertCanSeeTableRecords([$matchingEpisode])
        ->assertCanNotSeeTableRecords([$otherEpisode]);
});

it('is read-only and does not show header actions', function () {
    $show = Show::factory()->create();

    Livewire::test(EpisodesRelationManager::class, [
        'ownerRecord' => $show,
        'pageClass' => ViewShow::class,
    ])
        ->assertOk()
        ->assertDontSee('New episode')
        ->assertDontSee('Create');
});
