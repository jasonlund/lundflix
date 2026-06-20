<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithBytesizedStats;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BandwidthUsageWidget extends StatsOverviewWidget
{
    use InteractsWithBytesizedStats;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    protected ?string $pollingInterval = null;

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $account = $this->bytesizedPrimaryAccount;

        if ($account === null) {
            return [
                Stat::make('Bandwidth', '—')
                    ->description('Stats unavailable')
                    ->descriptionIcon('lucide-arrow-down-up')
                    ->color('gray'),
            ];
        }

        $usedBytes = (float) ($account['bandwidth_quota'] ?? 0);
        $totalBytes = (float) ($account['total_bandwidth'] ?? 0) * 1_000_000_000_000;
        $percent = $totalBytes > 0 ? ($usedBytes / $totalBytes) * 100 : 0.0;
        $server = $account['server_name'] ?? 'server';

        return [
            Stat::make('Bandwidth', round($percent).'%')
                ->description($this->formatBytes($usedBytes).' / '.$this->formatBytes($totalBytes).' on '.$server)
                ->descriptionIcon('lucide-arrow-down-up')
                ->color($this->usageColor($percent)),
        ];
    }
}
