<?php

use App\Models\User;
use App\Support\UserTime;
use Carbon\Carbon;

it('formats a UTC datetime in the user timezone', function () {
    $user = User::factory()->create(['timezone' => 'America/New_York']);
    $this->actingAs($user);

    // 2026-04-03 03:00 UTC = 2026-04-02 23:00 EDT
    $date = Carbon::parse('2026-04-03 03:00', 'UTC');

    expect(UserTime::format($date))->toBe('04/02/26');
});

it('formats with a custom format string', function () {
    $user = User::factory()->create(['timezone' => 'America/Los_Angeles']);
    $this->actingAs($user);

    // 2026-04-03 10:30 UTC = 2026-04-03 03:30 PDT
    $date = Carbon::parse('2026-04-03 10:30', 'UTC');

    expect(UserTime::format($date, 'g:i A'))->toBe('3:30 AM');
});

it('converts a UTC datetime to user timezone as Carbon', function () {
    $user = User::factory()->create(['timezone' => 'America/Chicago']);
    $this->actingAs($user);

    $date = Carbon::parse('2026-04-03 05:00', 'UTC');
    $converted = UserTime::toUserTz($date);

    expect($converted->format('Y-m-d H:i'))->toBe('2026-04-03 00:00')
        ->and($converted->timezoneName)->toBe('America/Chicago');
});

it('does not mutate the original Carbon instance', function () {
    $user = User::factory()->create(['timezone' => 'Asia/Tokyo']);
    $this->actingAs($user);

    $date = Carbon::parse('2026-04-03 12:00', 'UTC');
    UserTime::toUserTz($date);

    expect($date->timezoneName)->toBe('UTC');
});

it('falls back to America/New_York when unauthenticated', function () {
    // 2026-04-03 03:00 UTC = 2026-04-02 23:00 EDT
    $date = Carbon::parse('2026-04-03 03:00', 'UTC');

    expect(UserTime::format($date))->toBe('04/02/26');
});

it('converts airtime from source timezone to user timezone', function () {
    $user = User::factory()->create(['timezone' => 'America/Los_Angeles']);
    $this->actingAs($user);

    // 9 PM Eastern = 6 PM Pacific
    expect(UserTime::convertAirtime('21:00', 'America/New_York'))->toBe('6p');
});

it('converts airtime with minutes correctly', function () {
    $user = User::factory()->create(['timezone' => 'America/Chicago']);
    $this->actingAs($user);

    // 9:30 PM Eastern = 8:30 PM Central
    expect(UserTime::convertAirtime('21:30', 'America/New_York'))->toBe('8:30p');
});

it('converts airtime to AM format when crossing noon boundary', function () {
    $user = User::factory()->create(['timezone' => 'Europe/London']);
    $this->actingAs($user);

    // 9 PM Eastern = 2 AM BST (during summer)
    $this->travelTo(Carbon::parse('2026-07-01'));

    expect(UserTime::convertAirtime('21:00', 'America/New_York'))->toBe('2a');
});

it('returns the authenticated user timezone', function () {
    $user = User::factory()->create(['timezone' => 'Pacific/Auckland']);
    $this->actingAs($user);

    expect(UserTime::timezone())->toBe('Pacific/Auckland');
});

it('returns America/New_York when no user is authenticated', function () {
    expect(UserTime::timezone())->toBe('America/New_York');
});
