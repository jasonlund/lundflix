<?php

use App\Models\User;
use Illuminate\Support\Str;
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
        ->assertSee(Str::before(__('auth.failed'), "\n"))
        ->assertSee(Str::after(__('auth.failed'), "\n"))
        ->assertDontSee(__('lundbergh.form.email_description'))
        ->assertSeeHtml('data-flux-error-bubble');
});

it('redirects to the intended url after login', function () {
    $user = User::factory()->admin()->create();

    $this->get('/admin')->assertRedirect(route('login'));

    Livewire::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect('/admin');
});

it('shows plex error in password reset modal when pin creation fails', function () {
    $mockPlex = Mockery::mock(\App\Services\ThirdParty\PlexService::class);
    $mockPlex->shouldReceive('createPin')->once()->andThrow(new \RuntimeException('Plex API error'));

    app()->instance(\App\Services\ThirdParty\PlexService::class, $mockPlex);

    Livewire::test('auth.login')
        ->call('redirectToPlex', 'password_reset')
        ->assertSet('plexError', __('lundbergh.plex.pin_creation_failed'))
        ->assertSee(__('lundbergh.plex.pin_creation_failed'))
        ->assertNoRedirect();
});

it('shows the forgot password button', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Forgot Password?');
});

it('redirects to home when there is no intended url', function () {
    $user = User::factory()->create();

    Livewire::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect(route('home'));
});
