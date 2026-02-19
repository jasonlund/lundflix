<?php

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('can render the list page', function () {
    Livewire::test(ListUsers::class)
        ->assertSuccessful();
});

it('can render the view page', function () {
    $user = User::factory()->create();

    Livewire::test(ViewUser::class, ['record' => $user->getRouteKey()])
        ->assertSuccessful();
});

it('displays users in the list', function () {
    $user = User::factory()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);

    Livewire::test(ListUsers::class)
        ->assertSee('Test User')
        ->assertSee('test@example.com');
});

it('displays user details on view page', function () {
    $user = User::factory()->withPlex()->create([
        'name' => 'Detailed User',
        'email' => 'detailed@example.com',
    ]);

    Livewire::test(ViewUser::class, ['record' => $user->getRouteKey()])
        ->assertSee('Detailed User')
        ->assertSee('detailed@example.com');
});

it('does not show create button due to policy', function () {
    Livewire::test(ListUsers::class)
        ->assertDontSee('New user');
});

it('does not show delete action due to policy', function () {
    $user = User::factory()->create();

    Livewire::test(ViewUser::class, ['record' => $user->getRouteKey()])
        ->assertDontSee('Delete');
});

it('can filter users by role', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->filterTable('role', 'admin')
        ->assertCanSeeTableRecords([$admin, $this->admin])
        ->assertCanNotSeeTableRecords([$member]);
});

it('denies access to non-admin users', function () {
    $member = User::factory()->create();
    $this->actingAs($member);

    $this->get('/admin/users')
        ->assertForbidden();
});

it('denies access to server owners', function () {
    $serverOwner = User::factory()->serverOwner()->create();
    $this->actingAs($serverOwner);

    $this->get('/admin/users')
        ->assertForbidden();
});
