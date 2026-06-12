<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Concerns;

use App\Services\ThirdParty\BYSHService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Number;
use Livewire\Attributes\Computed;
use Throwable;

/**
 * @property-read array<string, mixed>|null $bytesizedPrimaryAccount
 */
trait InteractsWithBytesizedStats
{
    /**
     * The primary Bytesized Hosting account. Shared across all widget
     * instances via a 5-minute cache so the read-only API is hit at most
     * once per window. Successful lookups (including an empty account list)
     * are cached; API failures are not, so the next render retries instead
     * of locking in "unavailable" for the full window. Returns null when
     * the API is unreachable or no account exists.
     *
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function bytesizedPrimaryAccount(): ?array
    {
        $cached = Cache::get('bysh:primary-account');

        if (is_array($cached) && array_key_exists('account', $cached)) {
            return $cached['account'];
        }

        try {
            $account = app(BYSHService::class)->accounts()->first();
        } catch (Throwable) {
            return null;
        }

        Cache::put('bysh:primary-account', ['account' => $account], 300);

        return $account;
    }

    /**
     * Map a usage percentage to a Filament stat color.
     */
    protected function usageColor(float $percent): string
    {
        return match (true) {
            $percent >= 90 => 'danger',
            $percent >= 70 => 'warning',
            default => 'success',
        };
    }

    /**
     * Format a byte count using decimal (SI) units, matching the way
     * Bytesized Hosting displays disk and bandwidth (e.g. "3 TB", "2.17 TB").
     */
    protected function formatBytes(float $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $power = min((int) floor(log($bytes, 1000)), count($units) - 1);
        $value = $bytes / (1000 ** $power);

        return Number::format($value, maxPrecision: 2).' '.$units[$power];
    }
}
