<?php

use App\Models\User;

it('renders the demo command empty state with the Lundbergh bubble styling', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('demo'));

    $response->assertSuccessful()
        ->assertSee('Command (Empty State)')
        ->assertSee(__('lundbergh.empty.search_no_results'))
        ->assertSee('data-flux-command-empty');
});
