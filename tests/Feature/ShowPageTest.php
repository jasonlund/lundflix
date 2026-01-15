<?php

use App\Models\Show;
use Livewire\Livewire;

it('requires authentication to view show page', function () {
    $show = Show::factory()->create();

    $this->get(route('shows.show', $show))
        ->assertRedirect(route('login'));
});

it('displays show details', function () {
    $show = Show::factory()->create([
        'name' => 'Breaking Bad',
        'status' => 'Ended',
        'type' => 'Scripted',
        'runtime' => 60,
    ]);

    Livewire::test('shows.show', ['show' => $show])
        ->assertSee('Breaking Bad')
        ->assertSee('Ended')
        ->assertSee('Scripted')
        ->assertSee('60 min');
});

it('displays show with all details', function () {
    $show = Show::factory()->create([
        'name' => 'Game of Thrones',
        'type' => 'Scripted',
        'genres' => ['Drama', 'Fantasy'],
        'rating' => ['average' => 9.3],
        'network' => ['name' => 'HBO', 'country' => ['name' => 'United States']],
        'summary' => '<p>Seven noble families fight for control.</p>',
    ]);

    Livewire::test('shows.show', ['show' => $show])
        ->assertSee('Game of Thrones')
        ->assertSee('Scripted')
        ->assertSee('Drama')
        ->assertSee('Fantasy')
        ->assertSee('9.3')
        ->assertSee('HBO')
        ->assertSee('Seven noble families fight for control.');
});
