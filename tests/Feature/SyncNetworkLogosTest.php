<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();

    $this->testDir = storage_path('app/testing/logos');
    File::deleteDirectory($this->testDir);
});

afterEach(function () {
    File::deleteDirectory($this->testDir);
});

it('downloads logos from github', function () {
    $fakePng = file_get_contents(base_path('tests/Fixtures/pixel.png'));

    Http::fake([
        'raw.githubusercontent.com/*' => Http::response($fakePng, 200),
    ]);

    $this->artisan('logos:sync', ['--path' => $this->testDir])
        ->assertSuccessful();

    expect(File::exists("{$this->testDir}/networks/nbc-us.png"))->toBeTrue();
    expect(File::exists("{$this->testDir}/streaming/netflix.png"))->toBeTrue();
});

it('skips existing logos without force flag', function () {
    File::ensureDirectoryExists("{$this->testDir}/networks");
    File::put("{$this->testDir}/networks/nbc-us.png", 'existing');

    $fakePng = file_get_contents(base_path('tests/Fixtures/pixel.png'));

    Http::fake([
        'raw.githubusercontent.com/*' => Http::response($fakePng, 200),
    ]);

    $this->artisan('logos:sync', ['--path' => $this->testDir])
        ->assertSuccessful();

    expect(File::get("{$this->testDir}/networks/nbc-us.png"))->toBe('existing');
});

it('re-downloads existing logos with force flag', function () {
    File::ensureDirectoryExists("{$this->testDir}/networks");
    File::put("{$this->testDir}/networks/nbc-us.png", 'old');

    $fakePng = file_get_contents(base_path('tests/Fixtures/pixel.png'));

    Http::fake([
        'raw.githubusercontent.com/*' => Http::response($fakePng, 200),
    ]);

    $this->artisan('logos:sync', ['--path' => $this->testDir, '--force' => true])
        ->assertSuccessful();

    expect(File::get("{$this->testDir}/networks/nbc-us.png"))->not->toBe('old');
});

it('handles download failures gracefully', function () {
    Http::fake([
        'raw.githubusercontent.com/*' => Http::response('Not Found', 404),
    ]);

    $this->artisan('logos:sync', ['--path' => $this->testDir])
        ->assertFailed();
});
