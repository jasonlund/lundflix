<?php

use App\Enums\ShowStatus;
use App\Models\Show;
use Livewire\Livewire;

it('requires authentication to view show page', function () {
    $show = Show::factory()->create();

    $this->get(route('shows.show', $show))
        ->assertRedirect(route('login'));
});

it('displays show details', function () {
    $show = Show::factory()->create([
        'name' => 'Breaking Bad',
        'status' => ShowStatus::Ended->value,
        'runtime' => 60,
        'premiered' => now()->subYears(10),
        'ended' => now()->subYears(7),
    ]);

    Livewire::test('shows.show', ['show' => $show])
        ->assertSee('Breaking Bad')
        ->assertSee('Ended')
        ->assertSee('60 min')
        ->assertSeeHtml('class="relative h-[16rem] overflow-hidden"');
});

it('displays approximate runtime when only average_runtime is set', function () {
    $show = Show::factory()->create([
        'name' => 'Pluribus',
        'runtime' => null,
        'average_runtime' => 49,
    ]);

    Livewire::test('shows.show', ['show' => $show])
        ->assertSee('~49 min');
});

it('displays exact runtime without tilde when runtime is set', function () {
    $show = Show::factory()->create([
        'name' => 'The Wire',
        'runtime' => 60,
        'average_runtime' => 60,
    ]);

    Livewire::test('shows.show', ['show' => $show])
        ->assertSee('60 min')
        ->assertDontSee('~60 min');
});

it('displays no runtime when neither runtime nor average_runtime is set', function () {
    $show = Show::factory()->create([
        'runtime' => null,
        'average_runtime' => null,
    ]);

    expect($show->displayRuntime())->toBeNull();

    Livewire::test('shows.show', ['show' => $show])
        ->assertDontSeeHtml(' min</span>');
});

it('displays show with all details', function () {
    $show = Show::factory()->create([
        'name' => 'Game of Thrones',
        'genres' => ['Drama', 'Fantasy'],
        'network' => ['name' => 'HBO', 'country' => ['name' => 'United States']],
    ]);

    Livewire::test('shows.show', ['show' => $show])
        ->assertSee('Game of Thrones')
        ->assertSee('Drama')
        ->assertSee('Fantasy')
        ->assertSee('HBO')
        ->assertSee('IMDb');
});

it('displays compact schedule with single day and time', function () {
    $show = Show::factory()->create([
        'status' => ShowStatus::Running->value,
        'schedule' => ['days' => ['Monday'], 'time' => '21:00'],
    ]);

    Livewire::test('shows.show', ['show' => $show])
        ->assertSee('Mo 9p');
});

it('displays compact schedule with single day and no time', function () {
    $show = Show::factory()->create([
        'status' => ShowStatus::Running->value,
        'schedule' => ['days' => ['Friday'], 'time' => ''],
    ]);

    Livewire::test('shows.show', ['show' => $show])
        ->assertSee('Fr')
        ->assertDontSee('Fr ');
});

it('displays compact schedule with consecutive day range', function () {
    $show = Show::factory()->create([
        'status' => ShowStatus::Running->value,
        'schedule' => ['days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'], 'time' => ''],
    ]);

    Livewire::test('shows.show', ['show' => $show])
        ->assertSeeText('Mo–Fr');
});

it('displays compact schedule with non-consecutive days and time', function () {
    $show = Show::factory()->create([
        'status' => ShowStatus::Running->value,
        'schedule' => ['days' => ['Monday', 'Wednesday', 'Friday'], 'time' => '21:00'],
    ]);

    Livewire::test('shows.show', ['show' => $show])
        ->assertSee('Mo, We, Fr 9p');
});

it('hides schedule when days are empty', function () {
    $show = Show::factory()->create([
        'status' => ShowStatus::Running->value,
        'schedule' => ['days' => [], 'time' => ''],
    ]);

    $component = Livewire::test('shows.show', ['show' => $show]);

    expect($component->instance()->scheduleLabel())->toBeNull();
});

it('displays compact schedule with non-zero minutes', function () {
    $show = Show::factory()->create([
        'status' => ShowStatus::Running->value,
        'schedule' => ['days' => ['Sunday'], 'time' => '10:30'],
    ]);

    Livewire::test('shows.show', ['show' => $show])
        ->assertSee('Su 10:30a');
});

it('displays compact schedule with midnight time', function () {
    $show = Show::factory()->create([
        'status' => ShowStatus::Running->value,
        'schedule' => ['days' => ['Saturday'], 'time' => '00:00'],
    ]);

    Livewire::test('shows.show', ['show' => $show])
        ->assertSee('Sa 12a');
});

it('displays compact schedule with weekend range and time', function () {
    $show = Show::factory()->create([
        'status' => ShowStatus::Running->value,
        'schedule' => ['days' => ['Saturday', 'Sunday'], 'time' => '20:00'],
    ]);

    Livewire::test('shows.show', ['show' => $show])
        ->assertSee('Sa, Su 8p');
});

it('hides schedule for ended shows', function () {
    $show = Show::factory()->create([
        'status' => ShowStatus::Ended->value,
        'schedule' => ['days' => ['Monday'], 'time' => '21:00'],
    ]);

    Livewire::test('shows.show', ['show' => $show])
        ->assertDontSee('Mo 9p');
});

it('shows schedule for to-be-determined shows', function () {
    $show = Show::factory()->create([
        'status' => ShowStatus::ToBeDetermined->value,
        'schedule' => ['days' => ['Wednesday'], 'time' => '22:00'],
    ]);

    Livewire::test('shows.show', ['show' => $show])
        ->assertSee('We 10p');
});
