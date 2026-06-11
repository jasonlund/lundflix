<?php

use App\Filament\Widgets\BandwidthUsageWidget;
use App\Filament\Widgets\StorageUsageWidget;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    config()->set('services.bysh.api_key', 'test-key');
    Cache::flush();
    $this->actingAs(User::factory()->admin()->create());
});

function fakeBytesizedAccount(array $overrides = []): void
{
    Http::fake([
        'bytesized-hosting.com/api/v1/accounts.json*' => Http::response([array_merge([
            'server_name' => 'ajax',
            'disk_quota' => 1_500_000_000,        // KB used -> 1.5 TB of 3.0 TB = 50%
            'total_storage' => 3000,              // GB allowed
            'bandwidth_quota' => 5_000_000_000_000, // bytes used -> 5 TB of 10 TB = 50%
            'total_bandwidth' => 10,              // TB allowed
        ], $overrides)]),
    ]);
}

it('renders storage usage computed from raw quota over total', function () {
    fakeBytesizedAccount();

    Livewire::test(StorageUsageWidget::class)
        ->assertSee('51%')
        ->assertSee('3 TB')
        ->assertSee('ajax');
});

it('renders bandwidth usage computed from raw quota over total', function () {
    fakeBytesizedAccount();

    Livewire::test(BandwidthUsageWidget::class)
        ->assertSee('50%')
        ->assertSee('TB');
});

it('colors storage red when nearly full', function () {
    fakeBytesizedAccount(['disk_quota' => 2_900_000_000]); // ~2.97 TB of 3.0 TB = 99%

    Livewire::test(StorageUsageWidget::class)->assertSee('99%');
});

it('shows an unavailable state when the api fails', function () {
    Http::fake([
        'bytesized-hosting.com/api/v1/*' => Http::response('nope', 500),
    ]);

    Livewire::test(StorageUsageWidget::class)
        ->assertSee('Stats unavailable');
});

it('only calls the api once across both widgets via cache', function () {
    fakeBytesizedAccount();

    Livewire::test(StorageUsageWidget::class)->assertSee('51%');
    Livewire::test(BandwidthUsageWidget::class)->assertSee('50%');

    Http::assertSentCount(1);
});
