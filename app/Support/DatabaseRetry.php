<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Database\QueryException;

class DatabaseRetry
{
    private const TRANSIENT_CODES = [
        2002, // Connection refused
        2006, // MySQL server has gone away
    ];

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function run(Closure $callback, int $times = 3): mixed
    {
        return retry(
            $times,
            $callback,
            fn (int $attempt): int => $attempt * 1000,
            fn (\Throwable $e): bool => $e instanceof QueryException
                && in_array((int) $e->getCode(), self::TRANSIENT_CODES),
        );
    }
}
