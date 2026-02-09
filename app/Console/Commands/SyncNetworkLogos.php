<?php

namespace App\Console\Commands;

use App\Enums\NetworkLogo;
use App\Enums\StreamingLogo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

use function Laravel\Prompts\progress;

class SyncNetworkLogos extends Command
{
    private const BASE_URL = 'https://raw.githubusercontent.com/tv-logo/tv-logos/main/';

    protected $signature = 'logos:sync {--force : Re-download existing logos} {--path= : Custom output directory}';

    protected $description = 'Download network and streaming service logos from tv-logo/tv-logos';

    public function handle(): int
    {
        $entries = collect(NetworkLogo::cases())
            ->map(fn (NetworkLogo $logo) => ['file' => $logo->file(), 'source' => $logo->source(), 'type' => 'networks'])
            ->merge(collect(StreamingLogo::cases())
                ->map(fn (StreamingLogo $logo) => ['file' => $logo->file(), 'source' => $logo->source(), 'type' => 'streaming']))
            ->values();

        /** @var string|null $customPath */
        $customPath = $this->option('path');
        $basePath = $customPath ?: resource_path('images/logos');
        $force = $this->option('force');
        $downloaded = 0;
        $skipped = 0;
        $failed = 0;

        $progress = progress(label: 'Downloading logos', steps: $entries->count());
        $progress->start();

        foreach ($entries as $entry) {
            $destDir = "{$basePath}/{$entry['type']}";
            $destPath = "{$destDir}/{$entry['file']}";

            if (! $force && File::exists($destPath)) {
                $skipped++;
                $progress->advance();

                continue;
            }

            File::ensureDirectoryExists($destDir);

            $url = self::BASE_URL.$entry['source'];

            try {
                $response = Http::timeout(15)->get($url);

                if ($response->successful()) {
                    File::put($destPath, $response->body());
                    $downloaded++;
                } else {
                    $this->warn("  Failed ({$response->status()}): {$entry['file']}");
                    $failed++;
                }
            } catch (\Exception $e) {
                $this->warn("  Error downloading {$entry['file']}: {$e->getMessage()}");
                $failed++;
            }

            $progress->advance();
        }

        $progress->finish();

        $this->info("Done. Downloaded: {$downloaded}, Skipped: {$skipped}, Failed: {$failed}");

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
