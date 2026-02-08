<?php

use App\Models\User;

it('renders mobile-friendly navigation labels', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('home'));

    $response->assertSuccessful()
        ->assertSeeHtml('src="' . Vite::image('default-background.jpg') . '"')
        ->assertSeeHtml('<span class="sr-only sm:not-sr-only">Search</span>')
        ->assertSeeHtml('<span class="sr-only sm:not-sr-only">Logout</span>')
        ->assertSee('⌘K')
        ->assertDontSee('[&>div.text-xs]:hidden');
});
