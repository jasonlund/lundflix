<?php

use App\Support\AirDateTime;
use Carbon\Carbon;

it('resolves a non-override network to airdate plus airtime', function () {
    $result = AirDateTime::resolve('2026-04-03', '21:00', null);

    expect($result->format('Y-m-d H:i'))->toBe('2026-04-03 21:00');
});

it('resolves a non-override network with null airtime to midnight', function () {
    $result = AirDateTime::resolve('2026-04-03', null, null);

    expect($result->format('Y-m-d H:i'))->toBe('2026-04-03 00:00');
});

it('resolves an Apple TV+ episode to 6 PM Pacific the day before', function () {
    $webChannel = ['id' => 310, 'name' => 'Apple TV+'];

    $result = AirDateTime::resolve('2026-04-03', null, $webChannel);

    // 2026-04-02 18:00 PDT = 2026-04-03 01:00 UTC
    $expected = Carbon::parse('2026-04-02 18:00', 'America/Los_Angeles')->utc();

    expect($result->eq($expected))->toBeTrue();
});

it('ignores airtime for Apple TV+ and always uses 6 PM Pacific', function () {
    $webChannel = ['id' => 310, 'name' => 'Apple TV+'];

    $result = AirDateTime::resolve('2026-04-03', '21:00', $webChannel);

    $expected = Carbon::parse('2026-04-02 18:00', 'America/Los_Angeles')->utc();

    expect($result->eq($expected))->toBeTrue();
});

it('reports Apple TV+ episode as aired when past 6 PM Pacific the day before', function () {
    $webChannel = ['id' => 310, 'name' => 'Apple TV+'];

    // Travel to 2026-04-02 19:00 PDT (past the 6 PM drop)
    $this->travelTo(Carbon::parse('2026-04-02 19:00', 'America/Los_Angeles')->utc());

    expect(AirDateTime::hasAired('2026-04-03', null, $webChannel))->toBeTrue();
});

it('reports Apple TV+ episode as not aired when before 6 PM Pacific the day before', function () {
    $webChannel = ['id' => 310, 'name' => 'Apple TV+'];

    // Travel to 2026-04-02 17:00 PDT (before the 6 PM drop)
    $this->travelTo(Carbon::parse('2026-04-02 17:00', 'America/Los_Angeles')->utc());

    expect(AirDateTime::hasAired('2026-04-03', null, $webChannel))->toBeFalse();
});

it('reports non-override episode as aired when past airdate', function () {
    $this->travelTo(Carbon::parse('2026-04-04 00:00'));

    expect(AirDateTime::hasAired('2026-04-03', null, null))->toBeTrue();
});

it('reports non-override episode as not aired when before airdate', function () {
    $this->travelTo(Carbon::parse('2026-04-02 23:59'));

    expect(AirDateTime::hasAired('2026-04-03', null, null))->toBeFalse();
});

it('accepts a Carbon instance for airdate in hasAired', function () {
    $webChannel = ['id' => 310, 'name' => 'Apple TV+'];

    $this->travelTo(Carbon::parse('2026-04-02 19:00', 'America/Los_Angeles')->utc());

    expect(AirDateTime::hasAired(Carbon::parse('2026-04-03'), null, $webChannel))->toBeTrue();
});

it('returns effective cutoff of tomorrow for Apple TV+ when past 6 PM Pacific', function () {
    $webChannel = ['id' => 310, 'name' => 'Apple TV+'];

    // Travel to 2026-04-02 19:00 PDT
    $this->travelTo(Carbon::parse('2026-04-02 19:00', 'America/Los_Angeles')->utc());

    $cutoff = AirDateTime::effectiveAirDateCutoff($webChannel);

    expect($cutoff->format('Y-m-d'))->toBe('2026-04-03');
});

it('returns effective cutoff of today for Apple TV+ when before 6 PM Pacific', function () {
    $webChannel = ['id' => 310, 'name' => 'Apple TV+'];

    // Travel to 2026-04-02 10:00 PDT
    $this->travelTo(Carbon::parse('2026-04-02 10:00', 'America/Los_Angeles')->utc());

    $cutoff = AirDateTime::effectiveAirDateCutoff($webChannel);

    expect($cutoff->format('Y-m-d'))->toBe('2026-04-02');
});

it('returns effective cutoff of today for non-override networks', function () {
    $this->travelTo(Carbon::parse('2026-04-02 23:00'));

    $cutoff = AirDateTime::effectiveAirDateCutoff(null);

    expect($cutoff->format('Y-m-d'))->toBe('2026-04-02');
});

it('handles DST transition correctly for Apple TV+ spring forward', function () {
    $webChannel = ['id' => 310, 'name' => 'Apple TV+'];

    // 2026-03-08 is the spring-forward date (PST -> PDT)
    // Episode listed for 2026-03-09, should resolve to 2026-03-08 18:00 PDT
    $result = AirDateTime::resolve('2026-03-09', null, $webChannel);

    // 2026-03-08 18:00 PDT = 2026-03-09 01:00 UTC
    $expected = Carbon::parse('2026-03-08 18:00', 'America/Los_Angeles')->utc();

    expect($result->eq($expected))->toBeTrue();
});

it('handles DST transition correctly for Apple TV+ fall back', function () {
    $webChannel = ['id' => 310, 'name' => 'Apple TV+'];

    // 2026-11-01 is the fall-back date (PDT -> PST)
    // Episode listed for 2026-11-02, should resolve to 2026-11-01 18:00 PST
    $result = AirDateTime::resolve('2026-11-02', null, $webChannel);

    // 2026-11-01 18:00 PST = 2026-11-02 02:00 UTC
    $expected = Carbon::parse('2026-11-01 18:00', 'America/Los_Angeles')->utc();

    expect($result->eq($expected))->toBeTrue();
});

it('does not apply override for non-overridden streaming services', function () {
    $webChannel = ['id' => 1, 'name' => 'Netflix'];

    $result = AirDateTime::resolve('2026-04-03', '03:00', $webChannel);

    expect($result->format('Y-m-d H:i'))->toBe('2026-04-03 03:00');
});

it('resolves a Paramount+ episode to midnight Pacific on the listed date', function () {
    $webChannel = ['id' => 107, 'name' => 'Paramount+'];

    $result = AirDateTime::resolve('2026-04-03', null, $webChannel);

    $expected = Carbon::parse('2026-04-03 00:00', 'America/Los_Angeles')->utc();

    expect($result->eq($expected))->toBeTrue();
});

it('resolves an HBO Max episode to midnight Pacific on the listed date', function () {
    $webChannel = ['id' => 329, 'name' => 'Max'];

    $result = AirDateTime::resolve('2026-04-03', null, $webChannel);

    $expected = Carbon::parse('2026-04-03 00:00', 'America/Los_Angeles')->utc();

    expect($result->eq($expected))->toBeTrue();
});

it('reports Paramount+ episode as aired after midnight Pacific on the listed date', function () {
    $webChannel = ['id' => 107, 'name' => 'Paramount+'];

    // Travel to 2026-04-03 00:05 PDT (just after midnight drop)
    $this->travelTo(Carbon::parse('2026-04-03 00:05', 'America/Los_Angeles')->utc());

    expect(AirDateTime::hasAired('2026-04-03', null, $webChannel))->toBeTrue();
});

it('reports Paramount+ episode as not aired before midnight Pacific on the listed date', function () {
    $webChannel = ['id' => 107, 'name' => 'Paramount+'];

    // Travel to 2026-04-02 23:55 PDT (before midnight drop)
    $this->travelTo(Carbon::parse('2026-04-02 23:55', 'America/Los_Angeles')->utc());

    expect(AirDateTime::hasAired('2026-04-03', null, $webChannel))->toBeFalse();
});

it('returns effective cutoff of today for Paramount+ after midnight Pacific', function () {
    $webChannel = ['id' => 107, 'name' => 'Paramount+'];

    // Travel to 2026-04-03 01:00 PDT
    $this->travelTo(Carbon::parse('2026-04-03 01:00', 'America/Los_Angeles')->utc());

    $cutoff = AirDateTime::effectiveAirDateCutoff($webChannel);

    expect($cutoff->format('Y-m-d'))->toBe('2026-04-03');
});

it('returns effective cutoff of yesterday for HBO Max before midnight Pacific', function () {
    $webChannel = ['id' => 329, 'name' => 'Max'];

    // Travel to 2026-04-02 23:30 PDT (before midnight, so today's episodes haven't dropped)
    $this->travelTo(Carbon::parse('2026-04-02 23:30', 'America/Los_Angeles')->utc());

    $cutoff = AirDateTime::effectiveAirDateCutoff($webChannel);

    expect($cutoff->format('Y-m-d'))->toBe('2026-04-02');
});

it('adjusts Apple TV+ schedule by shifting days back and setting time to 6 PM', function () {
    $webChannel = ['id' => 310, 'name' => 'Apple TV+'];
    $schedule = ['days' => ['Friday'], 'time' => '00:00'];

    $adjusted = AirDateTime::adjustSchedule($schedule, $webChannel);

    expect($adjusted['days'])->toBe(['Thursday'])
        ->and($adjusted['time'])->toBe('18:00');
});

it('adjusts Apple TV+ schedule wrapping Sunday to Saturday', function () {
    $webChannel = ['id' => 310, 'name' => 'Apple TV+'];
    $schedule = ['days' => ['Sunday'], 'time' => ''];

    $adjusted = AirDateTime::adjustSchedule($schedule, $webChannel);

    expect($adjusted['days'])->toBe(['Saturday'])
        ->and($adjusted['time'])->toBe('18:00');
});

it('adjusts Apple TV+ schedule with multiple days', function () {
    $webChannel = ['id' => 310, 'name' => 'Apple TV+'];
    $schedule = ['days' => ['Wednesday', 'Friday'], 'time' => '12:00'];

    $adjusted = AirDateTime::adjustSchedule($schedule, $webChannel);

    expect($adjusted['days'])->toBe(['Tuesday', 'Thursday'])
        ->and($adjusted['time'])->toBe('18:00');
});

it('adjusts Paramount+ schedule time without shifting days', function () {
    $webChannel = ['id' => 107, 'name' => 'Paramount+'];
    $schedule = ['days' => ['Thursday'], 'time' => '03:00'];

    $adjusted = AirDateTime::adjustSchedule($schedule, $webChannel);

    expect($adjusted['days'])->toBe(['Thursday'])
        ->and($adjusted['time'])->toBe('0:00');
});

it('adjusts HBO Max schedule time without shifting days', function () {
    $webChannel = ['id' => 329, 'name' => 'Max'];
    $schedule = ['days' => ['Sunday'], 'time' => '21:00'];

    $adjusted = AirDateTime::adjustSchedule($schedule, $webChannel);

    expect($adjusted['days'])->toBe(['Sunday'])
        ->and($adjusted['time'])->toBe('0:00');
});

it('returns Pacific timezone for Paramount+ schedule', function () {
    $webChannel = ['id' => 107, 'name' => 'Paramount+'];

    expect(AirDateTime::scheduleTimezone(null, $webChannel))->toBe('America/Los_Angeles');
});

it('returns Pacific timezone for HBO Max schedule', function () {
    $webChannel = ['id' => 329, 'name' => 'Max'];

    expect(AirDateTime::scheduleTimezone(null, $webChannel))->toBe('America/Los_Angeles');
});

it('does not adjust schedule for non-Apple TV+ shows', function () {
    $schedule = ['days' => ['Monday'], 'time' => '21:00'];

    $adjusted = AirDateTime::adjustSchedule($schedule, null);

    expect($adjusted)->toBe($schedule);
});

it('does not adjust schedule for other streaming services', function () {
    $webChannel = ['id' => 1, 'name' => 'Netflix'];
    $schedule = ['days' => ['Friday'], 'time' => '03:00'];

    $adjusted = AirDateTime::adjustSchedule($schedule, $webChannel);

    expect($adjusted)->toBe($schedule);
});

it('returns Pacific timezone for Apple TV+ schedule', function () {
    $webChannel = ['id' => 310, 'name' => 'Apple TV+'];

    expect(AirDateTime::scheduleTimezone(null, $webChannel))->toBe('America/Los_Angeles');
});

it('returns network country timezone for broadcast shows', function () {
    $network = ['id' => 1, 'country' => ['timezone' => 'America/New_York']];

    expect(AirDateTime::scheduleTimezone($network, null))->toBe('America/New_York');
});

it('returns web channel country timezone when no network exists', function () {
    $webChannel = ['id' => 999, 'country' => ['timezone' => 'Europe/London']];

    expect(AirDateTime::scheduleTimezone(null, $webChannel))->toBe('Europe/London');
});

it('returns null when no timezone is available', function () {
    expect(AirDateTime::scheduleTimezone(null, null))->toBeNull();
    expect(AirDateTime::scheduleTimezone(['id' => 1], ['id' => 2]))->toBeNull();
});
