<?php

use App\Models\Show;
use Illuminate\Support\Facades\Vite;

beforeEach(function () {
    Vite::shouldReceive('asset')
        ->andReturnUsing(fn (string $path) => "/{$path}");
});

it('returns network logo url for a known network id', function () {
    $show = Show::factory()->make([
        'network' => ['id' => 1, 'name' => 'NBC', 'country' => ['name' => 'United States']],
    ]);

    expect($show->networkLogoUrl())->toContain('resources/images/logos/networks/nbc-us.png');
});

it('returns streaming logo url for a known streaming id', function () {
    $show = Show::factory()->make([
        'web_channel' => ['id' => 1, 'name' => 'Netflix'],
    ]);

    expect($show->streamingLogoUrl())->toContain('resources/images/logos/streaming/netflix.png');
});

it('returns null for unknown network id', function () {
    $show = Show::factory()->make([
        'network' => ['id' => 99999, 'name' => 'Unknown Network'],
    ]);

    expect($show->networkLogoUrl())->toBeNull();
});

it('returns null for unknown streaming id', function () {
    $show = Show::factory()->make([
        'web_channel' => ['id' => 99999, 'name' => 'Unknown'],
    ]);

    expect($show->streamingLogoUrl())->toBeNull();
});

it('returns null when network has no id key', function () {
    $show = Show::factory()->make([
        'network' => ['name' => 'NBC'],
    ]);

    expect($show->networkLogoUrl())->toBeNull();
});

it('returns null when network is null', function () {
    $show = Show::factory()->make([
        'network' => null,
    ]);

    expect($show->networkLogoUrl())->toBeNull();
});

it('returns null when web channel is null', function () {
    $show = Show::factory()->make([
        'web_channel' => null,
    ]);

    expect($show->streamingLogoUrl())->toBeNull();
});

it('handles uk network logos', function () {
    $show = Show::factory()->make([
        'network' => ['id' => 12, 'name' => 'BBC One', 'country' => ['name' => 'United Kingdom']],
    ]);

    expect($show->networkLogoUrl())->toContain('resources/images/logos/networks/bbc-one-uk.png');
});
