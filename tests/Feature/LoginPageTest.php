<?php

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
