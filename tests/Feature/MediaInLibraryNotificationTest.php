<?php

use App\Models\Episode;
use App\Models\Show;
use App\Notifications\MediaInLibraryNotification;
use Illuminate\Support\Facades\DB;

it('renders the show message without lazily loading the show relation', function () {
    $show = Show::factory()->create(['name' => 'Breaking Bad']);

    foreach (range(1, 4) as $number) {
        Episode::factory()->create([
            'show_id' => $show->id,
            'season' => 1,
            'number' => $number,
        ]);
    }

    // Mirror ProcessShowAvailability: episodes arrive as a plain Support collection
    // with no eager-loaded `show` relation.
    $episodes = collect(Episode::where('show_id', $show->id)->get()->all());

    $notification = new MediaInLibraryNotification($show, $episodes);

    DB::enableQueryLog();

    $message = $notification->toSlack(new stdClass);

    $showQueries = collect(DB::getQueryLog())
        ->filter(fn (array $entry) => str_contains($entry['query'], 'from "shows"'));

    expect($showQueries)->toBeEmpty();
    expect($message->toArray()['text'])->toContain('Breaking Bad');

    DB::disableQueryLog();
});

it('renders a clean show message with no episodes and no trailing space', function () {
    $show = Show::factory()->create(['name' => 'Breaking Bad']);

    $notification = new MediaInLibraryNotification($show, collect());

    $message = $notification->toSlack(new stdClass);

    expect($message->toArray()['text'])->toBe('Breaking Bad');

    $blockText = $message->toArray()['blocks'][0]['text']['text'];
    expect($blockText)->toBe("*📚 Show in Library*\n\nBreaking Bad");
});
