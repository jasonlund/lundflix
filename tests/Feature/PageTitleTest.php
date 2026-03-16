<?php

use App\Models\Movie;
use App\Models\Show;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['scout.driver' => 'collection']);
    $this->user = User::factory()->create();
    $this->suffix = config('app.name').', home of lundbergh';
});

it('shows page-specific title with suffix on dashboard', function () {
    $response = $this->actingAs($this->user)->get(route('home'));

    $response->assertOk();
    $response->assertSee("<title>Dashboard · {$this->suffix}</title>", false);
});

it('shows show name in title on show page', function () {
    $show = Show::factory()->create(['name' => 'Breaking Bad']);

    $response = $this->actingAs($this->user)->get(route('shows.show', $show));

    $response->assertOk();
    $response->assertSee("<title>Breaking Bad · {$this->suffix}</title>", false);
});

it('shows movie title in title on movie page', function () {
    $movie = Movie::factory()->create(['title' => 'The Matrix']);

    $response = $this->actingAs($this->user)->get(route('movies.show', $movie));

    $response->assertOk();
    $response->assertSee("<title>The Matrix · {$this->suffix}</title>", false);
});

it('shows sign in title on login page', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
    $response->assertSee("<title>Sign In · {$this->suffix}</title>", false);
});

it('shows error message in title on error pages', function () {
    config(['app.debug' => false]);

    Route::get('/test-404', fn () => abort(404));

    $response = $this->get('/test-404');

    $response->assertNotFound();
    $response->assertSee("<title>Not Found · {$this->suffix}</title>", false);
});

it('includes hostname prefix in local environment', function () {
    app()->detectEnvironment(fn () => 'local');

    $response = $this->actingAs($this->user)->get(route('home'));

    $response->assertOk();

    $hostname = Str::before(request()->getHost(), '.');
    $response->assertSee("<title>{$hostname} · Dashboard · {$this->suffix}</title>", false);
});
