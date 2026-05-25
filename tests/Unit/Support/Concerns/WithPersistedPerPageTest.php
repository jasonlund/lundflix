<?php

use App\Models\User;
use App\Support\Concerns\WithPersistedPerPage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function stubPerPageComponent(?array $options = null, ?int $default = null): object
{
    return new class($options, $default)
    {
        use WithPersistedPerPage;

        public bool $resetPageCalled = false;

        /** @param  list<int>|null  $options */
        public function __construct(private ?array $options, private ?int $default) {}

        protected function perPagePreferenceKey(): string
        {
            return 'tests.stub.per_page';
        }

        public function perPageOptions(): array
        {
            return $this->options ?? [5, 10, 20];
        }

        protected function defaultPerPage(): int
        {
            return $this->default ?? $this->perPageOptions()[0];
        }

        protected function paginatorPageName(): string
        {
            return 'stub_page';
        }

        public function resetPage(): void
        {
            $this->resetPageCalled = true;
        }
    };
}

it('uses the default per-page when no user is authenticated', function () {
    $component = stubPerPageComponent();

    $component->mountWithPersistedPerPage();

    expect($component->perPage)->toBe(5);
});

it('seeds per-page from the user preference when allowed', function () {
    $user = User::factory()->create();
    $user->preferences->set('tests.stub.per_page', 20);
    $user->save();
    $this->actingAs($user);

    $component = stubPerPageComponent();
    $component->mountWithPersistedPerPage();

    expect($component->perPage)->toBe(20);
});

it('falls back to default when stored per-page is outside options', function () {
    $user = User::factory()->create();
    $user->preferences->set('tests.stub.per_page', 99);
    $user->save();
    $this->actingAs($user);

    $component = stubPerPageComponent();
    $component->mountWithPersistedPerPage();

    expect($component->perPage)->toBe(5);
});

it('writes per-page to user preferences and resets pagination on update', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $component = stubPerPageComponent();
    $component->perPage = 10;
    $component->updatedPerPage();

    $user->refresh();

    expect((int) $user->preferences->get('tests.stub.per_page'))->toBe(10)
        ->and($component->resetPageCalled)->toBeTrue();
});

it('clamps invalid per-page values to default on update', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $component = stubPerPageComponent();
    $component->perPage = 99;
    $component->updatedPerPage();

    $user->refresh();

    expect($component->perPage)->toBe(5)
        ->and((int) $user->preferences->get('tests.stub.per_page'))->toBe(5);
});

it('honors custom options and default overrides', function () {
    $component = stubPerPageComponent(options: [25, 50, 100], default: 50);

    $component->mountWithPersistedPerPage();

    expect($component->perPage)->toBe(50);
});
