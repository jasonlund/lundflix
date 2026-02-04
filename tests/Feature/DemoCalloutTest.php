<?php

use App\Models\User;

it('renders the demo callouts', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('demo'));

    $response->assertSuccessful()
        ->assertSee('Callout')
        ->assertSee('Lundbergh says...')
        ->assertSee('Request received')
        ->assertSee('Something went wrong');
});
