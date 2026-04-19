<?php

declare(strict_types=1);

namespace App\Support;

use Sqids\Sqids;

class Sqid
{
    private static ?Sqids $instance = null;

    public static function instance(): Sqids
    {
        if (! self::$instance instanceof Sqids) {
            self::$instance = new Sqids(
                alphabet: config('sqids.alphabet') ?: 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789',
                minLength: (int) config('sqids.min_length', 8),
            );
        }

        return self::$instance;
    }

    public static function encode(int $id): string
    {
        return self::instance()->encode([$id]);
    }

    public static function decode(string $sqid): ?int
    {
        $decoded = self::instance()->decode($sqid);

        return $decoded[0] ?? null;
    }

    /**
     * Reset the cached instance (useful in tests when config changes).
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
