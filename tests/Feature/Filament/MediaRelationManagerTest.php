<?php

use App\Filament\RelationManagers\MediaRelationManager;
use App\Filament\Resources\Movies\Pages\ViewMovie;
use App\Filament\Resources\Shows\Pages\ViewShow;
use App\Models\Media;
use App\Models\Movie;
use App\Models\Show;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Http::preventStrayRequests();
    Storage::fake();
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
        'type' => 'movieposter',
        'likes' => 100,
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
        'type' => 'movieposter',
    ]);
    $logo = Media::factory()->create([
        'mediable_type' => Movie::class,
        'mediable_id' => $movie->id,
        'type' => 'hdmovielogo',
    ]);

    Livewire::test(MediaRelationManager::class, [
        'ownerRecord' => $movie,
        'pageClass' => ViewMovie::class,
    ])
        ->filterTable('type', 'movieposter')
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

it('sorts media by likes descending by default', function () {
    $movie = Movie::factory()->create();
    $lowLikes = Media::factory()->create([
        'mediable_type' => Movie::class,
        'mediable_id' => $movie->id,
        'likes' => 10,
    ]);
    $highLikes = Media::factory()->create([
        'mediable_type' => Movie::class,
        'mediable_id' => $movie->id,
        'likes' => 500,
    ]);

    Livewire::test(MediaRelationManager::class, [
        'ownerRecord' => $movie,
        'pageClass' => ViewMovie::class,
    ])
        ->assertCanSeeTableRecords([$highLikes, $lowLikes], inOrder: true);
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

it('can sync artwork from fanart api', function () {
    $movie = Movie::factory()->create(['imdb_id' => 'tt0111161']);

    Http::fake([
        'webservice.fanart.tv/v3/movies/tt0111161' => Http::response([
            'name' => 'The Shawshank Redemption',
            'imdb_id' => 'tt0111161',
            'movieposter' => [
                ['id' => '12345', 'url' => 'https://assets.fanart.tv/poster.jpg', 'lang' => 'en', 'likes' => '10'],
            ],
        ]),
        'assets.fanart.tv/*' => Http::response('fake-image-data'),
    ]);

    Livewire::test(MediaRelationManager::class, [
        'ownerRecord' => $movie,
        'pageClass' => ViewMovie::class,
    ])
        ->callAction(TestAction::make('syncArtwork')->table())
        ->assertNotified('Artwork synced');

    expect($movie->media()->count())->toBe(1);
    expect($movie->media()->first()->fanart_id)->toBe('12345');
});

it('shows warning when no artwork found', function () {
    $movie = Movie::factory()->create(['imdb_id' => 'tt9999999']);

    Http::fake([
        'webservice.fanart.tv/v3/movies/tt9999999' => Http::response([], 404),
    ]);

    Livewire::test(MediaRelationManager::class, [
        'ownerRecord' => $movie,
        'pageClass' => ViewMovie::class,
    ])
        ->callAction(TestAction::make('syncArtwork')->table())
        ->assertNotified('No artwork found');

    expect($movie->media()->count())->toBe(0);
});

it('displays media for a show', function () {
    $show = Show::factory()->create();
    $media = Media::factory()->create([
        'mediable_type' => Show::class,
        'mediable_id' => $show->id,
        'type' => 'tvposter',
    ]);

    Livewire::test(MediaRelationManager::class, [
        'ownerRecord' => $show,
        'pageClass' => ViewShow::class,
    ])
        ->assertOk()
        ->assertCanSeeTableRecords([$media]);
});

it('can sync artwork for a show from fanart api', function () {
    $show = Show::factory()->create(['thetvdb_id' => 264492]);

    Http::fake([
        'webservice.fanart.tv/v3/tv/264492' => Http::response([
            'name' => 'Under the Dome',
            'thetvdb_id' => '264492',
            'tvposter' => [
                ['id' => '22222', 'url' => 'https://assets.fanart.tv/poster.jpg', 'lang' => 'en', 'likes' => '7'],
            ],
        ]),
        'assets.fanart.tv/*' => Http::response('fake-image-data'),
    ]);

    Livewire::test(MediaRelationManager::class, [
        'ownerRecord' => $show,
        'pageClass' => ViewShow::class,
    ])
        ->callAction(TestAction::make('syncArtwork')->table())
        ->assertNotified('Artwork synced');

    expect($show->media()->count())->toBe(1);
    expect($show->media()->first()->fanart_id)->toBe('22222');
});

it('shows error when show has no tvdb id', function () {
    $show = Show::factory()->create(['thetvdb_id' => null]);

    Livewire::test(MediaRelationManager::class, [
        'ownerRecord' => $show,
        'pageClass' => ViewShow::class,
    ])
        ->callAction(TestAction::make('syncArtwork')->table())
        ->assertNotified('Missing TVDB ID');
});
