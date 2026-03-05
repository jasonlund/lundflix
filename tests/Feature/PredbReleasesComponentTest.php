<?php

use App\Enums\ReleaseQuality;
use App\Models\Movie;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    Http::preventStrayRequests();
});

function fakePredbSearchResponse(array $releases = []): array
{
    return ['api.predb.net*' => Http::response([
        'status' => 'success',
        'message' => '',
        'data' => $releases,
        'results' => count($releases),
        'time' => 0.05,
    ])];
}

function predbRelease(string $name, int $status = 0): array
{
    return [
        'id' => fake()->numberBetween(1000000, 9999999),
        'pretime' => now()->subDays(2)->timestamp,
        'release' => $name,
        'section' => 'X264',
        'files' => 42,
        'size' => 1500.0,
        'status' => $status,
        'reason' => '',
        'group' => 'GROUP',
        'genre' => '',
        'url' => '/rls/'.$name,
    ];
}

describe('window logic', function () {
    it('renders nothing when movie has no digital release date', function () {
        $user = User::factory()->create();
        $movie = Movie::factory()->create(['digital_release_date' => null]);

        $this->actingAs($user);

        Livewire::test('movies.predb-releases', ['movie' => $movie])
            ->assertDontSeeHtml('flux:icon');

        Http::assertNothingSent();
    });

    it('renders nothing when outside the 14-day pre-release window', function () {
        $user = User::factory()->create();
        $movie = Movie::factory()->create([
            'digital_release_date' => today()->addDays(15),
        ]);

        $this->actingAs($user);

        Livewire::test('movies.predb-releases', ['movie' => $movie])
            ->assertDontSeeHtml('flux:icon');

        Http::assertNothingSent();
    });

    it('renders nothing when past the 30-day post-release window', function () {
        $user = User::factory()->create();
        $movie = Movie::factory()->create([
            'digital_release_date' => today()->subDays(31),
        ]);

        $this->actingAs($user);

        Livewire::test('movies.predb-releases', ['movie' => $movie])
            ->assertDontSeeHtml('flux:icon');

        Http::assertNothingSent();
    });

    it('checks predb when within 14 days before digital release', function () {
        $user = User::factory()->create();
        $movie = Movie::factory()->create([
            'title' => 'Test Movie',
            'year' => 2024,
            'digital_release_date' => today()->addDays(10),
        ]);

        Http::fake(fakePredbSearchResponse([]));

        $this->actingAs($user);

        Livewire::test('movies.predb-releases', ['movie' => $movie])
            ->assertSuccessful();

        Http::assertSentCount(1);
    });

    it('checks predb when within 30 days after digital release', function () {
        $user = User::factory()->create();
        $movie = Movie::factory()->create([
            'title' => 'Test Movie',
            'year' => 2024,
            'digital_release_date' => today()->subDays(20),
        ]);

        Http::fake(fakePredbSearchResponse([]));

        $this->actingAs($user);

        Livewire::test('movies.predb-releases', ['movie' => $movie])
            ->assertSuccessful();

        Http::assertSentCount(1);
    });
});

describe('caching', function () {
    it('caches false for 12 hours when no quality release found', function () {
        $user = User::factory()->create();
        $movie = Movie::factory()->create([
            'title' => 'Test Movie',
            'year' => 2024,
            'digital_release_date' => today()->addDays(5),
        ]);

        Http::fake(fakePredbSearchResponse([]));

        $this->actingAs($user);

        Livewire::test('movies.predb-releases', ['movie' => $movie])
            ->assertSuccessful();

        $cacheKey = "predb:quality:{$movie->id}";
        expect(Cache::has($cacheKey))->toBeTrue();
        expect(Cache::get($cacheKey))->toBeFalse();
    });

    it('caches quality integer when quality release found', function () {
        $user = User::factory()->create();
        $movie = Movie::factory()->create([
            'title' => 'Test Movie',
            'year' => 2024,
            'digital_release_date' => today()->addDays(5),
        ]);

        Http::fake(fakePredbSearchResponse([
            predbRelease('Test.Movie.2024.1080p.WEB-DL.x264-GROUP'),
        ]));

        $this->actingAs($user);

        Livewire::test('movies.predb-releases', ['movie' => $movie])
            ->assertSuccessful();

        $cacheKey = "predb:quality:{$movie->id}";
        expect(Cache::has($cacheKey))->toBeTrue();
        expect(Cache::get($cacheKey))->toBe(ReleaseQuality::WEBDL->value);
    });

    it('uses cached data without making HTTP requests', function () {
        $user = User::factory()->create();
        $movie = Movie::factory()->create([
            'title' => 'Test Movie',
            'year' => 2024,
            'digital_release_date' => today()->addDays(5),
        ]);

        Cache::put("predb:quality:{$movie->id}", ReleaseQuality::WEBDL->value, now()->addHours(12));

        $this->actingAs($user);

        Livewire::test('movies.predb-releases', ['movie' => $movie])
            ->assertSuccessful();

        Http::assertNothingSent();
    });
});

describe('rendering', function () {
    it('shows green icon and quality label when quality release is found', function () {
        $user = User::factory()->create();
        $movie = Movie::factory()->create([
            'digital_release_date' => today()->addDays(5),
        ]);

        Cache::put("predb:quality:{$movie->id}", ReleaseQuality::WEBDL->value, now()->addHours(12));

        $this->actingAs($user);

        Livewire::test('movies.predb-releases', ['movie' => $movie])
            ->assertSeeHtml('text-green-500')
            ->assertSee('WEB-DL');
    });

    it('shows zinc icon when no quality release found yet', function () {
        $user = User::factory()->create();
        $movie = Movie::factory()->create([
            'digital_release_date' => today()->addDays(5),
        ]);

        Cache::put("predb:quality:{$movie->id}", false, now()->addHours(12));

        $this->actingAs($user);

        Livewire::test('movies.predb-releases', ['movie' => $movie])
            ->assertSeeHtml('text-zinc-500')
            ->assertDontSee('WEB-DL');
    });

    it('shows the correct label for different quality levels', function () {
        $user = User::factory()->create();
        $movie = Movie::factory()->create([
            'digital_release_date' => today()->addDays(5),
        ]);

        Cache::put("predb:quality:{$movie->id}", ReleaseQuality::BluRay->value, now()->addHours(12));

        $this->actingAs($user);

        Livewire::test('movies.predb-releases', ['movie' => $movie])
            ->assertSeeHtml('text-green-500')
            ->assertSee('Blu-Ray');
    });
});
