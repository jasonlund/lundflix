<?php

use App\Models\User;

it('redirects unauthenticated users to login', function () {
    $response = $this->get('/admin');

    $response->assertRedirect('/login');
});

it('denies access to members', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin')
        ->assertForbidden();
});

it('allows access to admins', function () {
    $user = User::factory()->admin()->create();

    $this->actingAs($user)
        ->get('/admin')
        ->assertSuccessful();
});

it('allows access to server owners', function () {
    $user = User::factory()->serverOwner()->create();

    $this->actingAs($user)
        ->get('/admin')
        ->assertSuccessful();
});
