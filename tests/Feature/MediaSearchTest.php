<?php

use App\Models\Movie;
use App\Models\Show;
use Livewire\Livewire;

it('navigates to show page when show result is selected', function () {
    $show = Show::factory()->create();

    Livewire::test('media-search')
        ->call('selectResult', 'show', $show->id)
        ->assertRedirect(route('shows.show', $show));
});

it('navigates to movie page when movie result is selected', function () {
    $movie = Movie::factory()->create();

    Livewire::test('media-search')
        ->call('selectResult', 'movie', $movie->id)
        ->assertRedirect(route('movies.show', $movie));
});
