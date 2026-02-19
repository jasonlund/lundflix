<?php

use App\Enums\IptCategory;
use App\Filament\Resources\Requests\Pages\ViewRequest;
use App\Filament\Resources\Requests\Widgets\IptSearchLinksWidget;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\Show;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('renders on the view request page', function () {
    $request = Request::factory()->create();

    Livewire::test(ViewRequest::class, ['record' => $request->getRouteKey()])
        ->assertSeeLivewire(IptSearchLinksWidget::class);
});

it('shows no links when request has no items', function () {
    $request = Request::factory()->create();

    $widget = Livewire::test(IptSearchLinksWidget::class, ['record' => $request]);

    $widget->assertDontSee('IPT Search Links');
});

it('shows movie links when request has only movie items', function () {
    $request = Request::factory()->create();
    $movie = Movie::factory()->create(['imdb_id' => 'tt0099999', 'title' => 'Test Movie', 'year' => 2020]);
    RequestItem::factory()->create([
        'request_id' => $request->id,
        'requestable_type' => Movie::class,
        'requestable_id' => $movie->id,
    ]);

    $widget = Livewire::test(IptSearchLinksWidget::class, ['record' => $request]);

    $widget->assertSee('IPT Search Links')
        ->assertSee('Test Movie (2020)')
        ->assertSee('tt0099999');
});

it('generates correct IPT search URLs for movies', function () {
    $request = Request::factory()->create();
    $movie = Movie::factory()->create(['imdb_id' => 'tt3333333']);
    RequestItem::factory()->create([
        'request_id' => $request->id,
        'requestable_type' => Movie::class,
        'requestable_id' => $movie->id,
    ]);

    $categories = IptCategory::queryString([IptCategory::MovieX265]);
    $expectedUrl = "https://iptorrents.com/t?{$categories}&q=".urlencode('tt3333333').'&qf=#torrents';

    $requestItem = RequestItem::where('request_id', $request->id)->first();

    $widget = Livewire::test(IptSearchLinksWidget::class, ['record' => $request]);

    $widget->assertActionHasUrl(TestAction::make('search')->table($requestItem), $expectedUrl);
});

it('handles mixed movies and episodes in the same request', function () {
    $request = Request::factory()->create();

    $movie = Movie::factory()->create(['imdb_id' => 'tt5000001', 'title' => 'Inception', 'year' => 2010]);
    $movieItem = RequestItem::factory()->create([
        'request_id' => $request->id,
        'requestable_type' => Movie::class,
        'requestable_id' => $movie->id,
    ]);

    $show = Show::factory()->create(['imdb_id' => 'tt5000002']);
    for ($i = 1; $i <= 5; $i++) {
        Episode::factory()->create([
            'show_id' => $show->id,
            'season' => 1,
            'number' => $i,
            'airdate' => now()->subMonths(6),
        ]);
    }
    $episode = Episode::where('show_id', $show->id)->where('number', 1)->first();
    $episodeItem = RequestItem::factory()->create([
        'request_id' => $request->id,
        'requestable_type' => Episode::class,
        'requestable_id' => $episode->id,
    ]);

    $movieCategories = IptCategory::queryString([IptCategory::MovieX265]);
    $tvCategories = IptCategory::queryString([IptCategory::TvPacks, IptCategory::TvX265]);

    $widget = Livewire::test(IptSearchLinksWidget::class, ['record' => $request]);

    $widget->assertSee('Inception (2010)')
        ->assertActionHasUrl(TestAction::make('search')->table($movieItem), "https://iptorrents.com/t?{$movieCategories}&q=".urlencode('tt5000001').'&qf=#torrents')
        ->assertActionHasUrl(TestAction::make('search')->table($episodeItem), "https://iptorrents.com/t?{$tvCategories}&q=".urlencode('tt5000002 s01e01').'&qf=#torrents');
});

it('uses MovieX265 category for movies and TV categories for episodes', function () {
    $request = Request::factory()->create();

    $movie = Movie::factory()->create(['imdb_id' => 'tt6000001']);
    $movieItem = RequestItem::factory()->create([
        'request_id' => $request->id,
        'requestable_type' => Movie::class,
        'requestable_id' => $movie->id,
    ]);

    $show = Show::factory()->create(['imdb_id' => 'tt6000002']);
    for ($i = 1; $i <= 10; $i++) {
        Episode::factory()->create([
            'show_id' => $show->id,
            'season' => 1,
            'number' => $i,
            'airdate' => now()->subMonths(6),
        ]);
    }
    $episode = Episode::where('show_id', $show->id)->where('number', 1)->first();
    $episodeItem = RequestItem::factory()->create([
        'request_id' => $request->id,
        'requestable_type' => Episode::class,
        'requestable_id' => $episode->id,
    ]);

    $movieCategories = IptCategory::queryString([IptCategory::MovieX265]);
    $tvCategories = IptCategory::queryString([IptCategory::TvPacks, IptCategory::TvX265]);

    $expectedMovieUrl = "https://iptorrents.com/t?{$movieCategories}&q=".urlencode('tt6000001').'&qf=#torrents';
    $expectedEpisodeUrl = "https://iptorrents.com/t?{$tvCategories}&q=".urlencode('tt6000002 s01e01').'&qf=#torrents';

    $widget = Livewire::test(IptSearchLinksWidget::class, ['record' => $request]);

    $widget->assertActionHasUrl(TestAction::make('search')->table($movieItem), $expectedMovieUrl);
    $widget->assertActionHasUrl(TestAction::make('search')->table($episodeItem), $expectedEpisodeUrl);
});

it('shows individual episode links for a partial season', function () {
    $request = Request::factory()->create();
    $show = Show::factory()->create(['imdb_id' => 'tt1234567']);

    // Create 5 episodes in season 1 but only request 2
    $episodes = collect();
    for ($i = 1; $i <= 5; $i++) {
        $episodes->push(Episode::factory()->create([
            'show_id' => $show->id,
            'season' => 1,
            'number' => $i,
            'airdate' => now()->subMonths(6),
        ]));
    }

    $item1 = RequestItem::factory()->create([
        'request_id' => $request->id,
        'requestable_type' => Episode::class,
        'requestable_id' => $episodes[0]->id,
    ]);
    $item2 = RequestItem::factory()->create([
        'request_id' => $request->id,
        'requestable_type' => Episode::class,
        'requestable_id' => $episodes[2]->id,
    ]);

    $categories = IptCategory::queryString([IptCategory::TvPacks, IptCategory::TvX265]);

    $widget = Livewire::test(IptSearchLinksWidget::class, ['record' => $request]);

    $widget->assertActionHasUrl(TestAction::make('search')->table($item1), "https://iptorrents.com/t?{$categories}&q=".urlencode('tt1234567 s01e01').'&qf=#torrents')
        ->assertActionHasUrl(TestAction::make('search')->table($item2), "https://iptorrents.com/t?{$categories}&q=".urlencode('tt1234567 s01e03').'&qf=#torrents');
});

it('shows a season link when all episodes of a completed season are requested', function () {
    $request = Request::factory()->create();
    $show = Show::factory()->create(['imdb_id' => 'tt9999999']);

    // Create 3 episodes in season 2, all aired
    $firstItem = null;
    for ($i = 1; $i <= 3; $i++) {
        $episode = Episode::factory()->create([
            'show_id' => $show->id,
            'season' => 2,
            'number' => $i,
            'airdate' => now()->subMonths(3),
        ]);

        $item = RequestItem::factory()->create([
            'request_id' => $request->id,
            'requestable_type' => Episode::class,
            'requestable_id' => $episode->id,
        ]);

        $firstItem ??= $item;
    }

    $categories = IptCategory::queryString([IptCategory::TvPacks, IptCategory::TvX265]);

    $widget = Livewire::test(IptSearchLinksWidget::class, ['record' => $request]);

    // Complete season consolidates to a single row with season query
    $widget->assertActionHasUrl(TestAction::make('search')->table($firstItem), "https://iptorrents.com/t?{$categories}&q=".urlencode('tt9999999 S02').'&qf=#torrents')
        ->assertDontSee('s02e01')
        ->assertDontSee('s02e02')
        ->assertDontSee('s02e03');
});

it('includes specials when determining complete season', function () {
    $request = Request::factory()->create();
    $show = Show::factory()->create(['imdb_id' => 'tt5555555']);

    // 2 regular episodes + 1 special in season 1
    $ep1 = Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 1,
        'airdate' => now()->subMonths(3),
    ]);
    $ep2 = Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 2,
        'airdate' => now()->subMonths(3),
    ]);
    $special = Episode::factory()->special()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 1,
        'airdate' => now()->subMonths(2),
    ]);

    // Request only the 2 regular episodes (not the special) - should be individual
    RequestItem::factory()->create([
        'request_id' => $request->id,
        'requestable_type' => Episode::class,
        'requestable_id' => $ep1->id,
    ]);
    RequestItem::factory()->create([
        'request_id' => $request->id,
        'requestable_type' => Episode::class,
        'requestable_id' => $ep2->id,
    ]);

    $widget = Livewire::test(IptSearchLinksWidget::class, ['record' => $request]);

    // Not a complete season (missing the special)
    $widget->assertSee('s01e01')
        ->assertSee('s01e02')
        ->assertDontSee('S01');
});

it('shows individual links for a currently running season even if all episodes are requested', function () {
    $request = Request::factory()->create();
    $show = Show::factory()->create(['imdb_id' => 'tt7777777']);

    // 3 episodes in season 1: 2 aired, 1 future
    $ep1 = Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 1,
        'airdate' => now()->subWeeks(2),
    ]);
    $ep2 = Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 2,
        'airdate' => now()->subWeek(),
    ]);
    $ep3 = Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 3,
        'airdate' => now()->addWeek(),
    ]);

    // Request all 3
    foreach ([$ep1, $ep2, $ep3] as $episode) {
        RequestItem::factory()->create([
            'request_id' => $request->id,
            'requestable_type' => Episode::class,
            'requestable_id' => $episode->id,
        ]);
    }

    $widget = Livewire::test(IptSearchLinksWidget::class, ['record' => $request]);

    // Currently running - should be individual
    $widget->assertSee('s01e01')
        ->assertSee('s01e02')
        ->assertSee('s01e03')
        ->assertDontSee('S01');
});

it('shows individual links when episodes have null airdates', function () {
    $request = Request::factory()->create();
    $show = Show::factory()->create(['imdb_id' => 'tt8888888']);

    $ep1 = Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 1,
        'airdate' => now()->subWeek(),
    ]);
    $ep2 = Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 2,
        'airdate' => null,
    ]);

    foreach ([$ep1, $ep2] as $episode) {
        RequestItem::factory()->create([
            'request_id' => $request->id,
            'requestable_type' => Episode::class,
            'requestable_id' => $episode->id,
        ]);
    }

    $widget = Livewire::test(IptSearchLinksWidget::class, ['record' => $request]);

    $widget->assertSee('s01e01')
        ->assertSee('s01e02')
        ->assertDontSee('S01');
});

it('generates correct IPT search URLs for individual episodes', function () {
    $request = Request::factory()->create();
    $show = Show::factory()->create(['imdb_id' => 'tt1111111']);

    // Create 10 episodes but only request 1 (partial season)
    for ($i = 1; $i <= 10; $i++) {
        Episode::factory()->create([
            'show_id' => $show->id,
            'season' => 3,
            'number' => $i,
            'airdate' => now()->subMonths(6),
        ]);
    }

    $episode = Episode::where('show_id', $show->id)->where('number', 7)->first();
    RequestItem::factory()->create([
        'request_id' => $request->id,
        'requestable_type' => Episode::class,
        'requestable_id' => $episode->id,
    ]);

    $categories = IptCategory::queryString([IptCategory::TvPacks, IptCategory::TvX265]);
    $expectedUrl = "https://iptorrents.com/t?{$categories}&q=".urlencode('tt1111111 s03e07').'&qf=#torrents';

    $requestItem = RequestItem::where('request_id', $request->id)->first();

    $widget = Livewire::test(IptSearchLinksWidget::class, ['record' => $request]);

    $widget->assertActionHasUrl(TestAction::make('search')->table($requestItem), $expectedUrl);
});

it('handles mixed complete and partial seasons from different shows', function () {
    $request = Request::factory()->create();

    $show1 = Show::factory()->create(['imdb_id' => 'tt1000001']);
    $show2 = Show::factory()->create(['imdb_id' => 'tt1000002']);

    // Show 1: complete season 1 (2 episodes, all aired, all requested)
    $show1FirstItem = null;
    for ($i = 1; $i <= 2; $i++) {
        $episode = Episode::factory()->create([
            'show_id' => $show1->id,
            'season' => 1,
            'number' => $i,
            'airdate' => now()->subMonths(6),
        ]);
        $item = RequestItem::factory()->create([
            'request_id' => $request->id,
            'requestable_type' => Episode::class,
            'requestable_id' => $episode->id,
        ]);
        $show1FirstItem ??= $item;
    }

    // Show 2: partial season 3 (3 episodes exist, only 1 requested)
    for ($i = 1; $i <= 3; $i++) {
        Episode::factory()->create([
            'show_id' => $show2->id,
            'season' => 3,
            'number' => $i,
            'airdate' => now()->subMonths(3),
        ]);
    }
    $show2Episode = Episode::where('show_id', $show2->id)->where('number', 2)->first();
    $show2Item = RequestItem::factory()->create([
        'request_id' => $request->id,
        'requestable_type' => Episode::class,
        'requestable_id' => $show2Episode->id,
    ]);

    $categories = IptCategory::queryString([IptCategory::TvPacks, IptCategory::TvX265]);

    $widget = Livewire::test(IptSearchLinksWidget::class, ['record' => $request]);

    // Show 1: season link
    $widget->assertActionHasUrl(TestAction::make('search')->table($show1FirstItem), "https://iptorrents.com/t?{$categories}&q=".urlencode('tt1000001 S01').'&qf=#torrents');

    // Show 2: individual link
    $widget->assertActionHasUrl(TestAction::make('search')->table($show2Item), "https://iptorrents.com/t?{$categories}&q=".urlencode('tt1000002 s03e02').'&qf=#torrents');
});

it('has a build link action for movie items', function () {
    $request = Request::factory()->create();
    $movie = Movie::factory()->create(['imdb_id' => 'tt7000001']);
    $movieItem = RequestItem::factory()->create([
        'request_id' => $request->id,
        'requestable_type' => Movie::class,
        'requestable_id' => $movie->id,
    ]);

    Livewire::test(IptSearchLinksWidget::class, ['record' => $request])
        ->assertActionExists(TestAction::make('buildLink')->table($movieItem));
});

it('has a build link action for episode items', function () {
    $request = Request::factory()->create();
    $show = Show::factory()->create(['imdb_id' => 'tt7000002']);
    for ($i = 1; $i <= 10; $i++) {
        Episode::factory()->create([
            'show_id' => $show->id,
            'season' => 1,
            'number' => $i,
            'airdate' => now()->subMonths(6),
        ]);
    }
    $episode = Episode::where('show_id', $show->id)->where('number', 1)->first();
    $episodeItem = RequestItem::factory()->create([
        'request_id' => $request->id,
        'requestable_type' => Episode::class,
        'requestable_id' => $episode->id,
    ]);

    Livewire::test(IptSearchLinksWidget::class, ['record' => $request])
        ->assertActionExists(TestAction::make('buildLink')->table($episodeItem));
});

it('pre-fills the build link modal with correct movie data', function () {
    $request = Request::factory()->create();
    $movie = Movie::factory()->create(['imdb_id' => 'tt7000003']);
    $movieItem = RequestItem::factory()->create([
        'request_id' => $request->id,
        'requestable_type' => Movie::class,
        'requestable_id' => $movie->id,
    ]);

    Livewire::test(IptSearchLinksWidget::class, ['record' => $request])
        ->mountAction(TestAction::make('buildLink')->table($movieItem))
        ->assertMountedActionModalSee('tt7000003')
        ->assertMountedActionModalSee('x265');
});

it('pre-fills the build link modal with correct episode data', function () {
    $request = Request::factory()->create();
    $show = Show::factory()->create(['imdb_id' => 'tt7000004']);
    for ($i = 1; $i <= 10; $i++) {
        Episode::factory()->create([
            'show_id' => $show->id,
            'season' => 2,
            'number' => $i,
            'airdate' => now()->subMonths(6),
        ]);
    }
    $episode = Episode::where('show_id', $show->id)->where('number', 5)->first();
    $episodeItem = RequestItem::factory()->create([
        'request_id' => $request->id,
        'requestable_type' => Episode::class,
        'requestable_id' => $episode->id,
    ]);

    Livewire::test(IptSearchLinksWidget::class, ['record' => $request])
        ->mountAction(TestAction::make('buildLink')->table($episodeItem))
        ->assertMountedActionModalSee('tt7000004 s02e05')
        ->assertMountedActionModalSee('Packs')
        ->assertMountedActionModalSee('x265')
        ->assertMountedActionModalSee('S02')
        ->assertMountedActionModalSee('s02e05');
});
