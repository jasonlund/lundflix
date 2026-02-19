<?php

use App\Enums\ShowStatus;
use App\Filament\Resources\Shows\Pages\ListShows;
use App\Filament\Resources\Shows\Pages\ViewShow;
use App\Filament\Resources\Shows\RelationManagers\EpisodesRelationManager;
use App\Models\Episode;
use App\Models\Show;
use App\Models\User;
use App\Services\TVMazeService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\Client\RequestException;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
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
        'status' => ShowStatus::Running->value,
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

it('shows "Fetch Episodes" button when show has no episodes', function () {
    $show = Show::factory()->create();

    Livewire::test(EpisodesRelationManager::class, [
        'ownerRecord' => $show,
        'pageClass' => ViewShow::class,
    ])
        ->assertSee('Fetch Episodes');
});

it('shows "Refresh Episodes" button when show has episodes', function () {
    $show = Show::factory()->create();
    Episode::factory()->for($show)->create();

    Livewire::test(EpisodesRelationManager::class, [
        'ownerRecord' => $show,
        'pageClass' => ViewShow::class,
    ])
        ->assertSee('Refresh Episodes');
});

it('imports episodes synchronously when fetch episodes action is called', function () {
    $show = Show::factory()->create(['tvmaze_id' => 123]);

    $mockEpisodes = [
        ['id' => 1, 'name' => 'Pilot', 'season' => 1, 'number' => 1],
        ['id' => 2, 'name' => 'Episode 2', 'season' => 1, 'number' => 2],
    ];

    $this->mock(TVMazeService::class)
        ->shouldReceive('episodes')
        ->with(123)
        ->once()
        ->andReturn($mockEpisodes);

    Livewire::test(EpisodesRelationManager::class, [
        'ownerRecord' => $show,
        'pageClass' => ViewShow::class,
    ])
        ->callAction(TestAction::make('fetchEpisodes')->table())
        ->assertNotified('Episodes imported');

    expect(Episode::where('show_id', $show->id)->count())->toBe(2)
        ->and(Episode::where('tvmaze_id', 1)->first()->name)->toBe('Pilot')
        ->and(Episode::where('tvmaze_id', 2)->first()->name)->toBe('Episode 2');
});

it('shows warning when API returns no episodes', function () {
    $show = Show::factory()->create(['tvmaze_id' => 456]);

    $this->mock(TVMazeService::class)
        ->shouldReceive('episodes')
        ->with(456)
        ->once()
        ->andReturn([]);

    Livewire::test(EpisodesRelationManager::class, [
        'ownerRecord' => $show,
        'pageClass' => ViewShow::class,
    ])
        ->callAction(TestAction::make('fetchEpisodes')->table())
        ->assertNotified('No episodes found');
});

it('shows error when API request fails', function () {
    $show = Show::factory()->create(['tvmaze_id' => 789]);

    $this->mock(TVMazeService::class)
        ->shouldReceive('episodes')
        ->with(789)
        ->once()
        ->andThrow(new RequestException(new \Illuminate\Http\Client\Response(new \GuzzleHttp\Psr7\Response(500))));

    Livewire::test(EpisodesRelationManager::class, [
        'ownerRecord' => $show,
        'pageClass' => ViewShow::class,
    ])
        ->callAction(TestAction::make('fetchEpisodes')->table())
        ->assertNotified('Failed to fetch episodes');
});

it('can search shows by name using Scout', function () {
    $matchingShow = Show::factory()->create([
        'name' => 'Breaking Bad',
        'imdb_id' => 'tt0903747',
    ]);
    $otherShow = Show::factory()->create([
        'name' => 'Better Call Saul',
        'imdb_id' => 'tt3032476',
    ]);

    Livewire::test(ListShows::class)
        ->searchTable('Breaking')
        ->assertCanSeeTableRecords([$matchingShow])
        ->assertCanNotSeeTableRecords([$otherShow]);
});

it('can search shows by imdb_id using Scout', function () {
    $matchingShow = Show::factory()->create([
        'name' => 'The Wire',
        'imdb_id' => 'tt0306414',
    ]);
    $otherShow = Show::factory()->create([
        'name' => 'The Sopranos',
        'imdb_id' => 'tt0141842',
    ]);

    Livewire::test(ListShows::class)
        ->searchTable('tt0306414')
        ->assertCanSeeTableRecords([$matchingShow])
        ->assertCanNotSeeTableRecords([$otherShow]);
});

it('can search shows by year using Scout', function () {
    $matchingShow = Show::factory()->create([
        'name' => 'Show From 2020',
        'premiered' => '2020-05-15',
    ]);
    $otherShow = Show::factory()->create([
        'name' => 'Show From 2010',
        'premiered' => '2010-03-20',
    ]);

    Livewire::test(ListShows::class)
        ->searchTable('2020')
        ->assertCanSeeTableRecords([$matchingShow])
        ->assertCanNotSeeTableRecords([$otherShow]);
});

it('returns no show results for non-matching search', function () {
    $show = Show::factory()->create([
        'name' => 'Game of Thrones',
        'imdb_id' => 'tt0944947',
    ]);

    Livewire::test(ListShows::class)
        ->searchTable('NonExistentShowXYZ123')
        ->assertCanNotSeeTableRecords([$show]);
});
