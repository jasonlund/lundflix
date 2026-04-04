<?php

namespace App\Support;

use Carbon\Carbon;

class UserTime
{
    /**
     * Convert a UTC Carbon datetime to the authenticated user's timezone and format it.
     */
    public static function format(Carbon $date, string $format = 'm/d/y'): string
    {
        return $date->copy()->setTimezone(self::timezone())->format($format);
    }

    /**
     * Convert a UTC Carbon datetime to the authenticated user's timezone.
     */
    public static function toUserTz(Carbon $date): Carbon
    {
        return $date->copy()->setTimezone(self::timezone());
    }

    /**
     * Convert a time string from a source timezone to the user's timezone,
     * returning a compact 12hr format like "8p" or "9:30a".
     */
    public static function convertAirtime(string $time, string $sourceTimezone): string
    {
        return self::convertAirtimeWithDayOffset($time, $sourceTimezone)['time'];
    }

    /**
     * Convert a time string from a source timezone to the user's timezone,
     * returning a compact 12hr format and the day offset (-1, 0, or +1).
     *
     * @return array{time: string, dayOffset: int}
     */
    public static function convertAirtimeWithDayOffset(string $time, string $sourceTimezone): array
    {
        $source = Carbon::createFromTimeString($time, $sourceTimezone);
        $converted = $source->copy()->setTimezone(self::timezone());

        $dayOffset = (int) $converted->format('j') - (int) $source->format('j');

        // Clamp to -1/0/+1 to handle month boundaries (e.g., 1st - 31st = -30, means +1)
        if ($dayOffset > 1) {
            $dayOffset = -1;
        } elseif ($dayOffset < -1) {
            $dayOffset = 1;
        }

        $suffix = $converted->format('a')[0];
        $formatted = ((int) $converted->format('i') === 0)
            ? $converted->format('g').$suffix
            : $converted->format('g:i').$suffix;

        return ['time' => $formatted, 'dayOffset' => $dayOffset];
    }

    /**
     * Get the authenticated user's timezone string.
     */
    public static function timezone(): string
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        return $user ? $user->timezone : 'America/New_York';
    }
}
