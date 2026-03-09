<?php

use App\Models\Request;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows the new user greeting when the user has no requests', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertSuccessful()
        ->assertSee(__('lundbergh.dashboard.greeting_new'), false);
});

it('shows the returning user greeting when the user has requests', function () {
    $user = User::factory()->create();
    Request::factory()->for($user)->create();

    $this->actingAs($user)
        ->get('/')
        ->assertSuccessful()
        ->assertSee(__('lundbergh.dashboard.greeting'), false);
});
