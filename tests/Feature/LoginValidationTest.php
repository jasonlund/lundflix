<?php

use Livewire\Livewire;

it('validates email is required', function () {
    Livewire::test('auth.login')
        ->set('email', '')
        ->set('password', 'password')
        ->call('login')
        ->assertHasErrors(['email' => 'required']);
});

it('validates email format on submit', function () {
    Livewire::test('auth.login')
        ->set('email', 'not-an-email')
        ->set('password', 'password')
        ->call('login')
        ->assertHasErrors(['email' => 'email']);
});

it('validates password is required', function () {
    Livewire::test('auth.login')
        ->set('email', 'test@example.com')
        ->set('password', '')
        ->call('login')
        ->assertHasErrors(['password' => 'required']);
});

it('passes validation with valid credentials format', function () {
    Livewire::test('auth.login')
        ->set('email', 'test@example.com')
        ->set('password', 'somepassword')
        ->assertHasNoErrors(['email', 'password']);
});
