<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Concerns;

use App\Services\ThirdParty\BYSHService;
use Illuminate\Support\Number;
use Livewire\Attributes\Computed;
use Throwable;

/**
 * @property-read array<string, mixed>|null $bytesizedPrimaryAccount
 */
trait InteractsWithBytesizedStats
{
    /**
     * The primary Bytesized Hosting account. Cached across all widget
     * instances for 5 minutes via Livewire's computed cache so the read-only
     * API is hit at most once per window. Returns null when the API is
     * unreachable or no account exists.
     *
     * @return array<string, mixed>|null
     */
    #[Computed(cache: true, key: 'bysh:primary-account', seconds: 300)]
    public function bytesizedPrimaryAccount(): ?array
    {
        try {
            return app(BYSHService::class)->accounts()->first();
        } catch (Throwable) {
            return null;
        }
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
