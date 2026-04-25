<?php

use App\Filament\Resources\SlackMessages\Pages\ListSlackMessages;
use App\Models\SlackMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('can render the list page', function () {
    Livewire::test(ListSlackMessages::class)
        ->assertSuccessful();
});

it('displays slack messages in the list', function () {
    SlackMessage::factory()->create([
        'content' => "*📝 New Request*\n\nInception (2010)",
    ]);

    Livewire::test(ListSlackMessages::class)
        ->assertSee('Inception (2010)');
});

it('renders slack mrkdwn links as readable anchors', function () {
    SlackMessage::factory()->create([
        'content' => "*☑️ Added to library on Main Plex*\n\n<https://lundflix.test/movies/inception|Inception (2010)>",
    ]);

    Livewire::test(ListSlackMessages::class)
        ->assertSee('Inception (2010)')
        ->assertDontSee('<https://lundflix.test/movies/inception|Inception (2010)>')
        ->assertSeeHtml('href="https://lundflix.test/movies/inception"');
});

it('can filter by type', function () {
    Livewire::test(ListSlackMessages::class)
        ->assertTableFilterExists('type');
});
