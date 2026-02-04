<?php

use App\Models\User;

it('renders the demo modal with the Lundbergh bubble styling', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('demo'));

    $response->assertSuccessful()
        ->assertSee('Lundbergh confirmation')
        ->assertSee('data-flux-modal-bubble')
        ->assertSee('mt-3 flex items-start gap-3');
});
