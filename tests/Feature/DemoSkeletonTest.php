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
        ->assertSee('rounded-2xl');

    // Should show one of the three random loading messages
    $loadingMessages = [
        __('lundbergh.loading.skeleton'),
        __('lundbergh.loading.please_wait'),
        __('lundbergh.loading.fetching'),
    ];

    $html = html_entity_decode($response->getContent());
    $foundMessage = false;
    foreach ($loadingMessages as $message) {
        if (str_contains($html, $message)) {
            $foundMessage = true;
            break;
        }
    }

    expect($foundMessage)->toBeTrue('Expected to find one of the loading messages');
});
