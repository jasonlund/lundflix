<?php

use App\Jobs\StoreShowEpisodes;
use App\Models\Show;
use Illuminate\Support\Facades\Queue;

it('renders the horizon dashboard in local environment', function () {
    $response = $this->get('/horizon');

    $response->assertSuccessful();
});

it('can dispatch jobs to the queue', function () {
    Queue::fake();

    $show = Show::factory()->create();
    $episodes = [
        ['id' => 1, 'name' => 'Test Episode', 'season' => 1, 'number' => 1, 'airdate' => null, 'runtime' => null],
    ];

    StoreShowEpisodes::dispatch($show, $episodes);

    Queue::assertPushed(StoreShowEpisodes::class);
});
