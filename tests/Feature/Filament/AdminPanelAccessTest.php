<?php

use App\Models\User;

it('redirects unauthenticated users to login', function () {
    $response = $this->get('/admin');

    $response->assertRedirect('/login');
});

it('denies access to users without matching plex token', function () {
    config(['services.plex.seed_token' => 'admin-secret-token']);

    $user = User::factory()->create([
        'plex_token' => 'different-token',
    ]);

    $this->actingAs($user)
        ->get('/admin')
        ->assertForbidden();
});

it('allows access to users with matching plex token', function () {
    config(['services.plex.seed_token' => 'admin-secret-token']);

    $user = User::factory()->create([
        'plex_token' => 'admin-secret-token',
    ]);

    $this->actingAs($user)
        ->get('/admin')
        ->assertSuccessful();
});

it('denies access when user has no plex token', function () {
    config(['services.plex.seed_token' => 'admin-secret-token']);

    $user = User::factory()->create([
        'plex_token' => null,
    ]);

    $this->actingAs($user)
        ->get('/admin')
        ->assertForbidden();
});

it('denies access when seed token is not configured', function () {
    config(['services.plex.seed_token' => null]);

    $user = User::factory()->create([
        'plex_token' => 'some-token',
    ]);

    $this->actingAs($user)
        ->get('/admin')
        ->assertForbidden();
});
