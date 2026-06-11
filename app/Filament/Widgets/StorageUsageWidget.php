<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithBytesizedStats;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StorageUsageWidget extends StatsOverviewWidget
{
    use InteractsWithBytesizedStats;

    protected static ?int $sort = 1;

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
                Stat::make('Storage', '—')
                    ->description('Stats unavailable')
                    ->descriptionIcon('lucide-hard-drive')
                    ->color('gray'),
            ];
        }

        $usedBytes = (float) ($account['disk_quota'] ?? 0) * 1_024;
        $totalBytes = (float) ($account['total_storage'] ?? 0) * 1_000_000_000;
        $percent = $totalBytes > 0 ? ($usedBytes / $totalBytes) * 100 : 0.0;
        $server = $account['server_name'] ?? 'server';

        return [
            Stat::make('Storage', round($percent).'%')
                ->description($this->formatBytes($usedBytes).' / '.$this->formatBytes($totalBytes).' on '.$server)
                ->descriptionIcon('lucide-hard-drive')
                ->color($this->usageColor($percent)),
        ];
    }
}
