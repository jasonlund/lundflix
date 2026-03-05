<?php

use App\Enums\ReleaseQuality;
use App\Models\Movie;
use App\Services\PreDBService;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public Movie $movie;

    private const WINDOW_BEFORE_MONTHS = 6;

    private const WINDOW_AFTER_MONTHS = 12;

    private const NOT_FOUND_CACHE_HOURS = 1;

    private const FOUND_CACHE_DAYS = 30;

    public function placeholder(): string
    {
        return <<<'HTML'
        <div></div>
        HTML;
    }

    #[Computed]
    public function highestQuality(): ?ReleaseQuality
    {
        if (! $this->isInWindow()) {
            return null;
        }

        $cacheKey = "predb:quality:{$this->movie->id}";

        if (Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);

            if ($cached === false) {
                return null;
            }

            return ReleaseQuality::tryFrom($cached);
        }

        $quality = app(PreDBService::class)->highestQuality($this->movie);

        if ($quality !== null) {
            Cache::put($cacheKey, $quality->value, now()->addDays(self::FOUND_CACHE_DAYS));
        } else {
            Cache::put($cacheKey, false, now()->addHours(self::NOT_FOUND_CACHE_HOURS));
        }

        return $quality;
    }

    public function isInWindow(): bool
    {
        if (! $this->movie->digital_release_date) {
            return false;
        }

        $windowStart = $this->movie->digital_release_date->copy()->subMonths(self::WINDOW_BEFORE_MONTHS);
        $windowEnd = $this->movie->digital_release_date->copy()->addMonths(self::WINDOW_AFTER_MONTHS);

        return today()->gte($windowStart) && today()->lte($windowEnd);
    }
};
?>

<div>
    @if ($this->highestQuality !== null)
        <div class="flex items-center gap-1.5">
            <flux:icon.circle-check variant="mini" class="text-green-500" />
            <span class="text-sm text-green-500">{{ $this->highestQuality->label() }}</span>
        </div>
    @elseif ($this->isInWindow())
        <flux:icon.clock variant="mini" class="text-zinc-500" />
    @endif
</div>
