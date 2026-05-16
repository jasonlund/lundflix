<?php

use App\Models\User;

it('renders mobile-friendly navigation labels', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('home'));

    $response->assertSuccessful()
        ->assertSeeHtml('src="'.Vite::image('default-background.svg').'"')
        ->assertSeeHtml('<span class="sr-only sm:not-sr-only">Search</span>')
        ->assertSee('Logout')
        ->assertSeeHtml('data-flux-avatar')
        ->assertSeeHtml('text-lundflix')
        ->assertDontSee('[&>div.text-xs]:hidden');
});

it('renders transparent navbar with Alpine scroll tracking', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('home'));

    $response->assertSuccessful()
        ->assertSee('x-on:scroll.window.passive', false)
        ->assertSee('backdrop-blur-sm', false)
        ->assertSee('drop-shadow-glow', false)
        ->assertSee('drop-shadow-none', false);
});

it('renders the credits modal trigger in the footer', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('home'));

    $response->assertSuccessful()
        ->assertSee('Credits')
        ->assertSee('TMDB API')
        ->assertSee('TVMaze')
        ->assertSee('IMDb');
});
