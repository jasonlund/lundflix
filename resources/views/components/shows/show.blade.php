<?php

use App\Enums\ShowStatus;
use App\Models\Show;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public Show $show;

    #[Computed]
    public function episodes(): Collection
    {
        return $this->show->episodes;
    }

    public function imdbUrl(): string
    {
        return "https://www.imdb.com/title/{$this->show->imdb_id}/";
    }

    public function yearRange(): ?string
    {
        if (! $this->show->premiered) {
            return null;
        }

        $startYear = $this->show->premiered->year;

        if ($this->show->ended) {
            return "{$startYear}–{$this->show->ended->year}";
        }

        if ($this->show->status === ShowStatus::Running) {
            return "{$startYear}–present";
        }

        return (string) $startYear;
    }

    public function runtimeText(): ?string
    {
        $runtime = $this->show->displayRuntime();

        if (! $runtime) {
            return null;
        }

        $prefix = $runtime['approximate'] ? '~' : '';

        return "{$prefix}{$runtime['value']} min";
    }

    #[Computed]
    public function backgroundUrl(): ?string
    {
        return $this->show->artUrl('background');
    }

    #[Computed]
    public function logoUrl(): ?string
    {
        return $this->show->artUrl('logo');
    }

    /**
     * @return array{prefix: string, label: string}|null
     */
    #[Computed]
    public function networkInfo(): ?array
    {
        if ($this->show->network) {
            $label = $this->show->network['name'];
            if (isset($this->show->network['country']['name'])) {
                $label .= " ({$this->show->network['country']['name']})";
            }

            return ['prefix' => 'Network:', 'label' => $label];
        }

        if ($this->show->web_channel) {
            return ['prefix' => 'Streaming:', 'label' => $this->show->web_channel['name']];
        }

        return null;
    }

    #[Computed]
    public function scheduleLabel(): ?string
    {
        if (! $this->show->schedule || ! ($this->show->schedule['days'] ?? false)) {
            return null;
        }

        $days = $this->show->schedule['days'];

        usort($days, fn ($a, $b) => Carbon::parse($a)->dayOfWeekIso - Carbon::parse($b)->dayOfWeekIso);

        $abbrevs = array_map(fn ($d) => Carbon::parse($d)->minDayName, $days);

        $dayLabel = $this->collapseDayRanges($abbrevs);

        $time = $this->show->schedule['time'] ?? '';
        $timeLabel = $time !== '' ? $this->formatCompactTime($time) : null;

        return $timeLabel ? "{$dayLabel} {$timeLabel}" : $dayLabel;
    }

    /**
     * Collapse consecutive abbreviated days into en-dash ranges.
     *
     * @param  list<string>  $abbrevs
     */
    private function collapseDayRanges(array $abbrevs): string
    {
        $isoMap = [];
        for ($iso = 1; $iso <= 7; $iso++) {
            $isoMap[Carbon::now()->startOfWeek()->addDays($iso - 1)->minDayName] = $iso;
        }

        $ranges = [];
        $currentRange = [$abbrevs[0]];

        for ($i = 1; $i < count($abbrevs); $i++) {
            if (($isoMap[$abbrevs[$i]] ?? 0) === ($isoMap[$abbrevs[$i - 1]] ?? 0) + 1) {
                $currentRange[] = $abbrevs[$i];
            } else {
                $ranges[] = $currentRange;
                $currentRange = [$abbrevs[$i]];
            }
        }
        $ranges[] = $currentRange;

        $parts = array_map(function (array $range) {
            if (count($range) >= 3) {
                return $range[0] . '–' . end($range);
            }

            return implode(', ', $range);
        }, $ranges);

        return implode(', ', $parts);
    }

    /**
     * Convert 24hr "HH:MM" to compact 12hr with 1-letter suffix.
     */
    private function formatCompactTime(string $time): string
    {
        $carbon = Carbon::createFromTimeString($time);
        $suffix = $carbon->format('a')[0];

        if ((int) $carbon->format('i') === 0) {
            return $carbon->format('g') . $suffix;
        }

        return $carbon->format('g:i') . $suffix;
    }

    public function render(): mixed
    {
        return $this->view()->layout('components.layouts.app', [
            'backgroundImage' => $this->backgroundUrl(),
        ]);
    }
};
?>

<div class="flex flex-col">
    <div class="relative h-[16rem] overflow-hidden">
        @if ($show->imdb_id)
            <div class="absolute top-4 right-4 z-10">
                <flux:tooltip content="View on IMDb">
                    <a
                        href="{{ $this->imdbUrl() }}"
                        target="_blank"
                        class="flex items-center justify-center rounded-lg bg-zinc-900 p-2 transition hover:bg-zinc-800"
                    >
                        <flux:icon.imdb class="size-8" />
                    </a>
                </flux:tooltip>
            </div>
        @endif

        <div class="relative flex h-full flex-col gap-4 px-4 py-5">
            <div>
                @if ($this->logoUrl())
                    <img
                        src="{{ $this->logoUrl() }}"
                        alt="{{ $show->name }} logo"
                        class="h-20 w-auto max-w-full drop-shadow"
                    />
                @else
                    <flux:heading size="xl">{{ $show->name }}</flux:heading>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-2 text-sm text-zinc-300">
                @if ($this->yearRange())
                    <span>{{ $this->yearRange() }}</span>
                @endif

                <flux:tooltip :content="$show->status->value">
                    <x-dynamic-component
                        :component="'flux::icon.' . $show->status->icon()"
                        variant="mini"
                        :class="$show->status->iconColorClass()"
                    />
                </flux:tooltip>

                @if ($show->status !== ShowStatus::Ended && $this->scheduleLabel())
                    <span>{{ $this->scheduleLabel() }}</span>
                @endif

                @if ($this->runtimeText())
                    <flux:icon.dot variant="micro" class="text-zinc-300" />
                    <span>{{ $this->runtimeText() }}</span>
                @endif
            </div>

            @if ($show->genres && count($show->genres))
                <div class="flex flex-wrap gap-2">
                    @foreach ($show->genres as $genre)
                        <x-genre-badge :$genre />
                    @endforeach
                </div>
            @endif

            @if ($this->networkInfo())
                <div class="flex flex-wrap items-center gap-3 text-sm text-zinc-400">
                    <span>
                        <span class="font-medium text-zinc-200">{{ $this->networkInfo()['prefix'] }}</span>
                        {{ $this->networkInfo()['label'] }}
                    </span>
                </div>
            @endif
        </div>
    </div>

    <div class="flex flex-col gap-8 px-4 sm:px-6">
        @if ($show->imdb_id)
            <livewire:shows.plex-availability :show="$show" lazy />
        @endif

        @if ($this->episodes()->isNotEmpty())
            <livewire:shows.episodes :show="$show" :episodes="$this->episodes()" />
        @else
            <livewire:shows.episodes :show="$show" lazy />
        @endif
    </div>
</div>
