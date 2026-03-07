<?php

use App\Enums\ArtworkType;
use App\Filament\RelationManagers\MediaRelationManager;
use App\Filament\Resources\Movies\Pages\ViewMovie;
use App\Filament\Resources\Shows\Pages\ViewShow;
use App\Models\Media;
use App\Models\Movie;
use App\Models\Show;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    Http::preventStrayRequests();
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('renders the media relation manager on movie view page', function () {
    $movie = Movie::factory()->create();

    Livewire::test(ViewMovie::class, ['record' => $movie->getRouteKey()])
        ->assertSeeLivewire(MediaRelationManager::class);
});

it('displays media in the relation manager', function () {
    $movie = Movie::factory()->create();
    $media = Media::factory()->create([
        'mediable_type' => Movie::class,
        'mediable_id' => $movie->id,
        'type' => ArtworkType::Poster->value,
        'vote_average' => 5.5,
    ]);

    Livewire::test(MediaRelationManager::class, [
        'ownerRecord' => $movie,
        'pageClass' => ViewMovie::class,
    ])
        ->assertOk()
        ->assertCanSeeTableRecords([$media]);
});

it('can filter media by type', function () {
    $movie = Movie::factory()->create();
    $poster = Media::factory()->create([
        'mediable_type' => Movie::class,
        'mediable_id' => $movie->id,
        'type' => ArtworkType::Poster->value,
    ]);
    $logo = Media::factory()->create([
        'mediable_type' => Movie::class,
        'mediable_id' => $movie->id,
        'type' => ArtworkType::Logo->value,
    ]);

    Livewire::test(MediaRelationManager::class, [
        'ownerRecord' => $movie,
        'pageClass' => ViewMovie::class,
    ])
        ->filterTable('type', ArtworkType::Poster->value)
        ->assertCanSeeTableRecords([$poster])
        ->assertCanNotSeeTableRecords([$logo]);
});

it('can filter media by language', function () {
    $movie = Movie::factory()->create();
    $englishMedia = Media::factory()->create([
        'mediable_type' => Movie::class,
        'mediable_id' => $movie->id,
        'lang' => 'en',
    ]);
    $germanMedia = Media::factory()->create([
        'mediable_type' => Movie::class,
        'mediable_id' => $movie->id,
        'lang' => 'de',
    ]);

    Livewire::test(MediaRelationManager::class, [
        'ownerRecord' => $movie,
        'pageClass' => ViewMovie::class,
    ])
        ->filterTable('lang', 'en')
        ->assertCanSeeTableRecords([$englishMedia])
        ->assertCanNotSeeTableRecords([$germanMedia]);
});

it('is read-only and does not show header actions', function () {
    $movie = Movie::factory()->create();

    Livewire::test(MediaRelationManager::class, [
        'ownerRecord' => $movie,
        'pageClass' => ViewMovie::class,
    ])
        ->assertOk()
        ->assertDontSee('New media')
        ->assertDontSee('Create');
});

it('sorts media by vote_average descending by default', function () {
    $movie = Movie::factory()->create();
    $lowRating = Media::factory()->create([
        'mediable_type' => Movie::class,
        'mediable_id' => $movie->id,
        'vote_average' => 2.0,
    ]);
    $highRating = Media::factory()->create([
        'mediable_type' => Movie::class,
        'mediable_id' => $movie->id,
        'vote_average' => 9.0,
    ]);

    Livewire::test(MediaRelationManager::class, [
        'ownerRecord' => $movie,
        'pageClass' => ViewMovie::class,
    ])
        ->assertCanSeeTableRecords([$highRating, $lowRating], inOrder: true);
});

it('shows fetch artwork button when no media exists', function () {
    $movie = Movie::factory()->create();

    Livewire::test(MediaRelationManager::class, [
        'ownerRecord' => $movie,
        'pageClass' => ViewMovie::class,
    ])
        ->assertSee('Fetch Artwork');
});

it('shows refresh artwork button when media exists', function () {
    $movie = Movie::factory()->create();
    Media::factory()->create([
        'mediable_type' => Movie::class,
        'mediable_id' => $movie->id,
    ]);

    Livewire::test(MediaRelationManager::class, [
        'ownerRecord' => $movie,
        'pageClass' => ViewMovie::class,
    ])
        ->assertSee('Refresh Artwork');
});

it('can sync artwork from tmdb api for movie', function () {
    $movie = Movie::factory()->create(['tmdb_id' => 278]);

    Http::fake([
        'api.themoviedb.org/3/movie/278*' => Http::response([
            'id' => 278,
            'images' => [
                'posters' => [
                    ['file_path' => '/poster.jpg', 'iso_639_1' => 'en', 'vote_average' => 5.0, 'vote_count' => 10, 'width' => 500, 'height' => 750],
                ],
                'backdrops' => [],
                'logos' => [],
            ],
        ]),
    ]);

    Livewire::test(MediaRelationManager::class, [
        'ownerRecord' => $movie,
        'pageClass' => ViewMovie::class,
    ])
        ->callAction(TestAction::make('syncArtwork')->table())
        ->assertNotified('Artwork synced');

    expect($movie->media()->count())->toBe(1)
        ->and($movie->media()->first()->file_path)->toBe('/poster.jpg');
});

it('displays media for a show', function () {
    $show = Show::factory()->create();
    $media = Media::factory()->create([
        'mediable_type' => Show::class,
        'mediable_id' => $show->id,
        'type' => ArtworkType::Poster->value,
    ]);

    Livewire::test(MediaRelationManager::class, [
        'ownerRecord' => $show,
        'pageClass' => ViewShow::class,
    ])
        ->assertOk()
        ->assertCanSeeTableRecords([$media]);
});

it('can sync artwork for a show from tmdb api', function () {
    $show = Show::factory()->create(['tmdb_id' => 1396]);

    Http::fake([
        'api.themoviedb.org/3/tv/1396*' => Http::response([
            'id' => 1396,
            'images' => [
                'posters' => [
                    ['file_path' => '/show_poster.jpg', 'iso_639_1' => 'en', 'vote_average' => 7.0, 'vote_count' => 25, 'width' => 500, 'height' => 750],
                ],
                'backdrops' => [],
                'logos' => [],
            ],
        ]),
    ]);

    Livewire::test(MediaRelationManager::class, [
        'ownerRecord' => $show,
        'pageClass' => ViewShow::class,
    ])
        ->callAction(TestAction::make('syncArtwork')->table())
        ->assertNotified('Artwork synced');

    expect($show->media()->count())->toBe(1)
        ->and($show->media()->first()->file_path)->toBe('/show_poster.jpg');
});

it('shows error when model has no tmdb id', function () {
    $show = Show::factory()->create(['tmdb_id' => null]);

    Livewire::test(MediaRelationManager::class, [
        'ownerRecord' => $show,
        'pageClass' => ViewShow::class,
    ])
        ->callAction(TestAction::make('syncArtwork')->table())
        ->assertNotified('Missing TMDB ID');
});
