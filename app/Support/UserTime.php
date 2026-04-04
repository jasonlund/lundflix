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
        $carbon = Carbon::createFromTimeString($time, $sourceTimezone)
            ->setTimezone(self::timezone());

        $suffix = $carbon->format('a')[0];

        if ((int) $carbon->format('i') === 0) {
            return $carbon->format('g').$suffix;
        }

        return $carbon->format('g:i').$suffix;
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
