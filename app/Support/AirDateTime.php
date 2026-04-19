<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\Carbon;

class AirDateTime
{
    private const DEFAULT_TIMEZONE = 'America/Los_Angeles';

    /**
     * Streaming service release time overrides, keyed by TVMaze web channel ID.
     *
     * @var array<int, array{timezone: string, hour: int, dayOffset: int}>
     */
    private const OVERRIDES = [
        310 => ['timezone' => 'America/Los_Angeles', 'hour' => 18, 'dayOffset' => -1], // Apple TV+
        107 => ['timezone' => 'America/Los_Angeles', 'hour' => 0, 'dayOffset' => 0],   // Paramount+
        329 => ['timezone' => 'America/Los_Angeles', 'hour' => 0, 'dayOffset' => 0],   // HBO Max
    ];

    /**
     * Resolve when an episode actually becomes available.
     *
     * @param  array<string, mixed>|null  $webChannel  The show's web_channel data from TVMaze
     * @param  array<string, mixed>|null  $network  The show's network data from TVMaze
     */
    public static function resolve(string $airdate, ?string $airtime, ?array $webChannel, ?array $network = null): Carbon
    {
        $override = self::overrideFor($webChannel);

        if ($override) {
            return Carbon::parse($airdate, $override['timezone'])
                ->addDays($override['dayOffset'])
                ->setTime($override['hour'], 0)
                ->utc();
        }

        $sourceTimezone = self::scheduleTimezone($network, $webChannel) ?? self::DEFAULT_TIMEZONE;

        return Carbon::parse($airdate.' '.($airtime ?? '00:00'), $sourceTimezone)->utc();
    }

    /**
     * Check if an episode has aired based on network-specific release schedules.
     *
     * @param  array<string, mixed>|null  $webChannel
     * @param  array<string, mixed>|null  $network
     */
    public static function hasAired(string|Carbon $airdate, ?string $airtime, ?array $webChannel, ?array $network = null): bool
    {
        $airdateString = $airdate instanceof Carbon ? $airdate->format('Y-m-d') : $airdate;

        return self::resolve($airdateString, $airtime, $webChannel, $network)->lte(now());
    }

    /**
     * Get the effective air date cutoff for SQL date comparisons.
     *
     * For services with overrides, if the current time in the service's timezone
     * is past the drop hour, episodes with that day's air date (+ day offset
     * adjustment) are already available — so the cutoff shifts accordingly.
     *
     * @param  array<string, mixed>|null  $webChannel
     * @param  array<string, mixed>|null  $network
     */
    public static function effectiveAirDateCutoff(?array $webChannel, ?array $network = null): Carbon
    {
        $override = self::overrideFor($webChannel);

        if ($override) {
            $localNow = now($override['timezone']);
            $localToday = $localNow->copy()->startOfDay();

            if ($localNow->hour >= $override['hour']) {
                // Episodes for "today + abs(dayOffset)" are already available
                return $localToday->subDays($override['dayOffset']);
            }

            // Before the drop hour, only yesterday's (adjusted) episodes are out
            return $localToday->subDays($override['dayOffset'])->subDay();
        }

        $sourceTimezone = self::scheduleTimezone($network, $webChannel);

        return today($sourceTimezone ?? self::DEFAULT_TIMEZONE);
    }

    /**
     * Adjust a TVMaze schedule to reflect the actual release schedule.
     *
     * For services with overrides, applies the day offset (shifting days back/forward)
     * and sets the time to the actual drop time.
     *
     * @param  array{days?: list<string>, time?: string}  $schedule
     * @param  array<string, mixed>|null  $webChannel
     * @return array{days?: list<string>, time?: string}
     */
    public static function adjustSchedule(array $schedule, ?array $webChannel): array
    {
        $override = self::overrideFor($webChannel);

        if (! $override) {
            return $schedule;
        }

        $days = $schedule['days'] ?? [];

        $adjustedDays = $override['dayOffset'] !== 0
            ? array_map(fn (string $day): string => Carbon::parse($day)->addDays($override['dayOffset'])->format('l'), $days)
            : $days;

        return [
            ...$schedule,
            'days' => $adjustedDays,
            'time' => sprintf('%d:%02d', $override['hour'], 0),
        ];
    }

    /**
     * Get the timezone that a show's schedule time is expressed in.
     *
     * For services with overrides, returns the override timezone.
     * For broadcast networks, it's the network's country timezone.
     * For other web channels, it's the channel's country timezone.
     * Returns null when no timezone is available.
     *
     * @param  array<string, mixed>|null  $network
     * @param  array<string, mixed>|null  $webChannel
     */
    public static function scheduleTimezone(?array $network, ?array $webChannel): ?string
    {
        $override = self::overrideFor($webChannel);

        if ($override) {
            return $override['timezone'];
        }

        return $network['country']['timezone']
            ?? $webChannel['country']['timezone']
            ?? null;
    }

    /**
     * @param  array<string, mixed>|null  $webChannel
     * @return array{timezone: string, hour: int, dayOffset: int}|null
     */
    private static function overrideFor(?array $webChannel): ?array
    {
        if (! isset($webChannel['id'])) {
            return null;
        }

        return self::OVERRIDES[$webChannel['id']] ?? null;
    }
}
