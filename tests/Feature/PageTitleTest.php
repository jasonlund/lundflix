<?php

use App\Models\User;
use Illuminate\Support\Str;

it('shows app name as title in non-local environments', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('home'));

    $response->assertStatus(200);

    // In testing/production, title should just be the app name
    $appName = config('app.name');

    $response->assertSee("<title>{$appName}</title>", false);
});

it('includes hostname prefix in page title in local environment', function () {
    // Override app environment to local
    app()->detectEnvironment(fn () => 'local');

    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('home'));

    $response->assertStatus(200);

    // In local environment, title should be "{hostname} - {app_name}"
    $appName = config('app.name');
    $hostname = Str::before(request()->getHost(), '.');

    $response->assertSee("<title>{$hostname} - {$appName}</title>", false);
});
