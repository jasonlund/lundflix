<?php

namespace App\Support;

use Carbon\CarbonInterval;

class Formatters
{
    public static function runtime(?int $minutes): ?string
    {
        if ($minutes === null || $minutes <= 0) {
            return null;
        }

        return CarbonInterval::minutes($minutes)->cascade()->forHumans(['short' => true]);
    }
}
