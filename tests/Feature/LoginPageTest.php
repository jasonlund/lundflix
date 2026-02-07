<?php

use App\Models\User;
use Livewire\Livewire;

it('shows the lundbergh login hint bubble', function () {
    $response = $this->get(route('login'));

    $response
        ->assertOk()
        ->assertSee(__('lundbergh.form.email_description'))
        ->assertSee('lundbergh-head');
});

it('shows the lundbergh bubble for password errors', function () {
    Livewire::test('auth.login')
        ->set('email', 'test@example.com')
        ->set('password', 'not-the-right-password')
        ->call('login')
        ->assertSee(__('auth.failed'))
        ->assertDontSee(__('lundbergh.form.email_description'))
        ->assertSeeHtml('data-flux-error-bubble');
});

it('redirects to the intended url after login', function () {
    config(['services.plex.seed_token' => 'admin-secret-token']);

    $user = User::factory()->create([
        'plex_token' => 'admin-secret-token',
    ]);

    $this->get('/admin')->assertRedirect(route('login'));

    Livewire::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect('/admin');
});

it('redirects to home when there is no intended url', function () {
    $user = User::factory()->create();

    Livewire::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect(route('home'));
});
