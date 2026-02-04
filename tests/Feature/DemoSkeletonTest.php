<?php

use App\Models\User;

it('renders the demo skeleton with the Lundbergh bubble styling', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('demo'));

    $response->assertSuccessful()
        ->assertSee('Skeleton')
        ->assertSee('data-flux-skeleton')
        ->assertSee('data-flux-skeleton-bubble')
        ->assertSee('data-flux-skeleton-text')
        ->assertSee('animate-[flux-shimmer_2s_infinite]')
        ->assertSee(__('lundbergh.loading.skeleton'))
        ->assertSee(__('lundbergh.loading.please_wait'))
        ->assertSee(__('lundbergh.loading.fetching'))
        ->assertSee('rounded-2xl');
});
